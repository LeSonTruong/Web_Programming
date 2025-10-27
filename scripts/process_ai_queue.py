#!/usr/bin/env python3
"""
Worker to process ai_queue: picks pending rows, marks processing, uploads document file to AI service,
stores result in log and marks done/failed.

Usage: python scripts/process_ai_queue.py

Environment variables (optional): DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT
"""
import os
import sys
import time
import json
import traceback
import pymysql
from pymysql.cursors import DictCursor
from pathlib import Path

# Ensure project root is on sys.path so `includes` package can be imported when running
# the script from the scripts/ directory (or any cwd).
PROJECT_ROOT = Path(__file__).resolve().parents[1]
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

# Try to load .env from project root (one level up from scripts/)
try:
    dotenv_path = Path(__file__).resolve().parents[1] / '.env'
    try:
        from dotenv import load_dotenv
        load_dotenv(dotenv_path)
    except Exception:
        # python-dotenv not installed; do a lightweight .env parse as fallback
        try:
            if dotenv_path.exists():
                for line in dotenv_path.read_text(encoding='utf-8').splitlines():
                    line = line.strip()
                    if not line or line.startswith('#') or '=' not in line:
                        continue
                    k, v = line.split('=', 1)
                    k = k.strip()
                    v = v.strip().strip('"').strip("'")
                    # only set if not already present in environment
                    if k and os.environ.get(k) is None:
                        os.environ[k] = v
        except Exception:
            pass
except Exception:
    pass

from includes.ai_service import summarize_document_by_id

SLEEP_SECONDS = 3

def get_db_config():
    """Get database configuration from environment variables"""
    db_config = {
        'host': os.environ.get('DB_HOST', '127.0.0.1'),
        'user': os.environ.get('DB_USER', 'root'),
        'password': os.environ.get('DB_PASS') or os.environ.get('DB_PASSWORD', ''),
        'database': os.environ.get('DB_NAME') or os.environ.get('DB_DATABASE'),
        'port': int(os.environ.get('DB_PORT') or os.environ.get('PORT') or 3306),
        'charset': 'utf8mb4',
        'cursorclass': DictCursor
    }
    if not db_config['database']:
        raise RuntimeError('DB_NAME or DB_DATABASE environment variable must be set')
    return db_config

def get_next_pending():
    """Get next pending job from ai_queue"""
    db_config = get_db_config()
    
    with pymysql.connect(**db_config) as conn:
        with conn.cursor() as c:
            # Try to atomically claim a job
            c.execute("SELECT id FROM ai_queue WHERE status='pending' ORDER BY created_at ASC LIMIT 1")
            row = c.fetchone()
            if not row:
                return None
            
            job_id = row['id']
            # Try to claim it
            c.execute("UPDATE ai_queue SET status='processing', updated_at=NOW() WHERE id=%s AND status='pending'", (job_id,))
            if c.rowcount == 0:
                conn.commit()
                return None
            conn.commit()
            
            # Get full job details
            c.execute("SELECT * FROM ai_queue WHERE id=%s", (job_id,))
            return c.fetchone()

def update_queue(queue_id, status, log_msg=None, checkstatus=None):
    """Update ai_queue record - ONLY status, log, checkstatus columns exist"""
    db_config = get_db_config()
    with pymysql.connect(**db_config) as conn:
        with conn.cursor() as c:
            if checkstatus is not None:
                c.execute(
                    "UPDATE ai_queue SET status=%s, log=%s, checkstatus=%s, updated_at=NOW() WHERE id=%s",
                    (status, log_msg, checkstatus, queue_id)
                )
            else:
                c.execute(
                    "UPDATE ai_queue SET status=%s, log=%s, updated_at=NOW() WHERE id=%s",
                    (status, log_msg, queue_id)
                )
        conn.commit()

def update_document_from_ai_result(doc_id, result):
    """Update documents table based on AI result"""
    try:
        checkstatus = result.get('checkstatus') if isinstance(result, dict) else None
        cs_val = None
        if checkstatus is not None:
            try:
                cs_val = int(checkstatus)
            except Exception:
                pass
        
        db_config = get_db_config()
        with pymysql.connect(**db_config) as conn:
            with conn.cursor() as c:
                if cs_val == 0:
                    # Check còn chờ duyệt không
                    c.execute("SELECT status_id, user_id, title FROM documents WHERE doc_id=%s LIMIT 1", (doc_id,))
                    doc_row = c.fetchone()
                    if doc_row and doc_row.get('status_id') == 1:
                        # Từ chối
                        c.execute("UPDATE documents SET status_id=%s, updated_at=NOW() WHERE doc_id=%s", (3, doc_id))
                        # gửi tb
                        owner_id = doc_row.get('user_id')
                        title = doc_row.get('title') or ''
                        message = f"❌ Tài liệu '{title}' của bạn đã bị từ chối tự động bởi AI!"
                        c.execute("INSERT INTO notifications (user_id, message) VALUES (%s, %s)", (owner_id, message))
                        print(f'Document {doc_id} rejected by AI (checkstatus=0)')
                    else:
                        print(f'Document {doc_id} failed the check but not rejected by AI because it is no longer waiting for approval')
                else:
                    # Save summary to documents if present
                    summary = result.get('summary') if isinstance(result, dict) else None
                    category = result.get('category') if isinstance(result, dict) else None
                    
                    if summary is not None:
                        c.execute("UPDATE documents SET summary=%s, updated_at=NOW() WHERE doc_id=%s", (summary, doc_id))
                        print(f'Document {doc_id} summary updated')
                    
                    # Map category to subject_id
                    subject_id = None
                    if category:
                        # Try exact match first
                        c.execute("SELECT subject_id FROM subjects WHERE subject_name=%s LIMIT 1", (category,))
                        row = c.fetchone()
                        if row and 'subject_id' in row:
                            subject_id = row['subject_id']
                        else:
                            # Try case-insensitive match
                            c.execute("SELECT subject_id FROM subjects WHERE LOWER(subject_name)=LOWER(%s) LIMIT 1", (category,))
                            row2 = c.fetchone()
                            if row2 and 'subject_id' in row2:
                                subject_id = row2['subject_id']
                    
                    if subject_id is not None:
                        c.execute("UPDATE documents SET subject_id=%s, updated_at=NOW() WHERE doc_id=%s", (subject_id, doc_id))
                        print(f'Document {doc_id} subject set to {subject_id} (category={category})')
            conn.commit()
    except Exception as e:
        tb = traceback.format_exc()
        print(f'Error updating document {doc_id}: {e}\n{tb}')

def process_one():
    """Process one job from the queue. Returns True if job was processed, False if no jobs."""
    task = get_next_pending()
    if not task:
        return False
    
    queue_id = task["id"]
    doc_id = task["document_id"]
    print(f'Claimed job {queue_id} for document {doc_id}')
    
    try:
        db_config = get_db_config()
        conn = pymysql.connect(**db_config)
        try:
            result = summarize_document_by_id(doc_id, db_config=conn, timeout=120)
            print(f'Job {queue_id} finished, processing AI response')
            
            # --- Xử lý checkstatus để chuẩn hóa về 0 hoặc 1 ---
            raw_checkstatus = result.get('checkstatus')
            cs_val = None
            if isinstance(raw_checkstatus, list):
                # Ví dụ: [1] hoặc [0]
                try:
                    cs_val = int(raw_checkstatus[0])
                except Exception:
                    cs_val = None
            elif isinstance(raw_checkstatus, str):
                # "0" hoặc "1"
                try:
                    cs_val = int(raw_checkstatus)
                except Exception:
                    cs_val = None
            elif isinstance(raw_checkstatus, (int, float)):
                cs_val = int(raw_checkstatus)
            else:
                cs_val = None

            update_document_from_ai_result(doc_id, result)

            full_log = json.dumps(result, ensure_ascii=False)
            # Lưu trạng thái "done", log và checkstatus vào bảng ai_queue
            update_queue(queue_id, "done", full_log, cs_val)

            print(f'Job {queue_id} completed successfully')
        finally:
            conn.close()
        
    except Exception as e:
        tb = traceback.format_exc()
        print(f'Job {queue_id} failed: {e}\n{tb}')
        error_msg = f"Error: {str(e)}"
        update_queue(queue_id, "failed", error_msg[:2000])
    
    return True

def process_loop():
    """Main worker loop"""
    print('Worker started, connected to DB')
    print(f'Polling every {SLEEP_SECONDS} seconds for new jobs...')
    
    try:
        while True:
            try:
                has_job = process_one()
                if not has_job:
                    print("No pending jobs")
                    time.sleep(SLEEP_SECONDS)
                # If we processed a job, immediately check for more (no sleep)
            except Exception as e:
                tb = traceback.format_exc()
                print(f'Unexpected error in worker loop: {e}\n{tb}')
                time.sleep(SLEEP_SECONDS)
                
    except KeyboardInterrupt:
        print('\nWorker interrupted by user, exiting gracefully...')

if __name__ == '__main__':
    process_loop()
