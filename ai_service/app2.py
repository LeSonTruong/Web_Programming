import pymysql
import time
from transformers import AutoTokenizer, MBartForConditionalGeneration
import torch
from math import ceil

# --- Config ---
MODEL_DIR = "./models"
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "1234",
    "db": "web_programming",
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor
}

# --- Khởi tạo model/tokenizer ---
print("Đang load model ...")
tokenizer = AutoTokenizer.from_pretrained(MODEL_DIR)
model = MBartForConditionalGeneration.from_pretrained(MODEL_DIR)
print("AI model loaded.")

def summarize_chunk(text_chunk, lang="vi_VN", max_summary_len=256, min_summary_len=50):
    tokenizer.src_lang = lang
    inputs = tokenizer(text_chunk, return_tensors="pt", max_length=1024, truncation=True)
    with torch.no_grad():
        ids = model.generate(
            inputs.input_ids,
            max_length=max_summary_len,
            min_length=min_summary_len,
            num_beams=2,
            early_stopping=True,
            do_sample=False,
            length_penalty=1.0
        )
    return tokenizer.batch_decode(ids, skip_special_tokens=True)[0].strip()

def process_ai_hierarchical(text, lang="vi_VN",
                            chunk_tokens=1024, overlap_tokens=128,
                            per_chunk_max=256, per_chunk_min=50,
                            final_max=512, final_min=50):
    # Basic moderation
    if not kiem_duyet_don_gian(text):
        return False, None, "Tài liệu không đạt kiểm duyệt"

    # Tokenize entire text to ids
    enc = tokenizer(text, return_tensors="pt", add_special_tokens=False)
    input_ids = enc["input_ids"][0].tolist()

    # split into chunks with overlap
    chunks = []
    start = 0
    total = len(input_ids)
    while start < total:
        end = start + chunk_tokens
        chunk_ids = input_ids[start:end]
        chunks.append(tokenizer.decode(chunk_ids, skip_special_tokens=True, clean_up_tokenization_spaces=True))
        if end >= total:
            break
        start = end - overlap_tokens  # overlap

    intermediate_summaries = []
    for i, ch in enumerate(chunks):
        try:
            s = summarize_chunk(ch, lang=lang, max_summary_len=per_chunk_max, min_summary_len=per_chunk_min)
            intermediate_summaries.append(s)
        except Exception as e:
            intermediate_summaries.append("")  # keep position

    # If only one chunk, return that summary
    if len(intermediate_summaries) == 1:
        final_text = intermediate_summaries[0]
    else:
        # Join intermediate summaries and if still too long, summarize again in multiple passes
        joined = "\n\n".join([s for s in intermediate_summaries if s])
        # If joined is longer than ~chunk_tokens, we can recursively chunk summaries, but usually we can pass to final summarizer
        final_text = summarize_chunk(joined, lang=lang, max_summary_len=final_max, min_summary_len=final_min)

    return True, final_text, "Xử lý thành công"

# Usage: replace call to process_ai(doc_text) with process_ai_hierarchical(doc_text)

def kiem_duyet_don_gian(text):
    tu_cam = ["bạo lực", "phản động", "spam"]
    if len(text.strip()) < 10:
        return False
    if any(word in text.lower() for word in tu_cam):
        return False
    return True

def process_ai(text, lang="vi_VN"):
    if not kiem_duyet_don_gian(text):
        return False, None, "Tài liệu không đạt kiểm duyệt"
    # Giới hạn số từ phù hợp để tránh quá dài
    max_length_words = 2500
    if len(text.split()) > max_length_words:
        text = " ".join(text.split()[:max_length_words])
    tokenizer.src_lang = lang
    inputs = tokenizer(text, return_tensors="pt", max_length=512, truncation=True)
    with torch.no_grad():
        summary_ids = model.generate(
            inputs.input_ids,
            max_length=256,
            min_length=20,
        )
    summary = tokenizer.batch_decode(summary_ids, skip_special_tokens=True)[0]
    return True, summary, "Xử lý thành công"

def get_next_pending():
    with pymysql.connect(**DB_CONFIG) as conn:
        with conn.cursor() as cur:
            cur.execute("""
                SELECT aq.id, aq.doc_id, d.file_path
                FROM ai_logs aq
                JOIN documents d ON aq.doc_id = d.doc_id
                WHERE aq.status = 'pending'
                ORDER BY aq.created_at ASC
                LIMIT 1
            """)
            return cur.fetchone()

def update_queue(queue_id, status, summary=None, log_msg=None):
    with pymysql.connect(**DB_CONFIG) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE ai_logs SET status=%s, summary=%s, log=%s, updated_at=NOW() WHERE id=%s",
                (status, summary, log_msg, queue_id)
            )
            conn.commit()

def main():
    while True:
        task = get_next_pending()
        if not task:
            print("Không có tài liệu mới, nghỉ 15s...")
            time.sleep(15)
            continue
        print(f"Processing queue_id={task['id']}, doc_id={task['doc_id']}...")
        update_queue(task["id"], "processing", None, "Bắt đầu xử lý")

        try:
            with open(task["file_path"], "r", encoding="utf-8") as f:
                doc_text = f.read()
            passed, summary, log_msg = process_ai(doc_text)
            if passed:
                update_queue(task["id"], "done", summary, log_msg)
            else:
                update_queue(task["id"], "failed", None, log_msg)
            print(f"✓ Đã xử lý xong queue_id={task['id']}")
        except Exception as e:
            update_queue(task["id"], "failed", None, f"Lỗi: {str(e)}")
            print(f"[ERROR] queue_id={task['id']} Lỗi: {e}")

if __name__ == "__main__":
    main()
