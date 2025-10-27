"""
Simple AI client helper ported from includes/ai.js to Python.

Behavior:
- AI_URL: endpoint to POST to (expects multipart/form-data with field 'file').
- summarize_pdf(input, timeout=None, session=None)
    - `input` may be a path to a local file or a file-like object; the file is uploaded
        as multipart/form-data under field name 'file'.
    - Returns the parsed JSON response (requests.json()).
    - Raises requests.HTTPError on non-2xx responses (with helpful message).

Usage examples:
        from includes.ai_service import summarize_pdf

        # From a local file path
        res = summarize_pdf('/path/to/doc.pdf', timeout=60)

        # From an open file-like object
        with open('doc.pdf', 'rb') as f:
                res = summarize_pdf(f, timeout=60)

Note: This is a thin client. For server-side/background processing (recommended for
long-running jobs), enqueue a job on the server and process in a worker instead of calling
AI directly per-request.
"""

from typing import Optional, IO, Union
import requests
import os
import os.path
import json
from pathlib import Path

# For DB access when summarizing by doc_id
try:
    import pymysql
    from pymysql.cursors import DictCursor
except Exception:
    pymysql = None
    DictCursor = None

# Try to load .env from project root (one level up from includes/)
try:
    dotenv_path = Path(__file__).resolve().parents[1] / '.env'
    try:
        from dotenv import load_dotenv
        load_dotenv(dotenv_path)
    except Exception:
        # python-dotenv not installed or load failed; fall back to existing env vars
        pass
except Exception:
    pass

AI_URL = "http://127.0.0.1:8000/process_pdf"  # local 


def summarize_pdf(input: Union[os.PathLike, IO[bytes]], timeout: Optional[float] = None, session: Optional[requests.Session] = None) -> dict:
    """
    Send a PDF to the AI endpoint and return parsed JSON.

    - input: either a URL string (http/https) or a file path or a file-like object opened in binary mode.
    - timeout: seconds for requests; passed to requests.post.
    - session: optional requests.Session to reuse connections.

    Raises:
      requests.HTTPError on non-2xx responses (the exception message contains status and body text where possible).
      ValueError for invalid input.

    Returns parsed JSON (dict).
    """
    s = session or requests.Session()

    # File-like or path case: send multipart/form-data with 'file'
    file_obj = None
    filename = None
    close_after = False

    # If input is a file-like object
    if hasattr(input, 'read') and hasattr(input, 'tell'):
        file_obj = input
        filename = getattr(input, 'name', 'document')
    else:
        # treat as path-like
        path = str(input)
        if not os.path.exists(path):
            raise ValueError(f"File not found: {path}")
        file_obj = open(path, 'rb')
        filename = os.path.basename(path)
        close_after = True

    try:
        files = {'file': (filename, file_obj)}
        try:
            r = s.post(AI_URL, files=files, timeout=timeout)
        except requests.RequestException:
            raise
        if not r.ok:
            text = r.text[:2000] if r.text else ''
            raise requests.HTTPError(f"AI HTTP {r.status_code}: {text}", response=r)
        return r.json()
    finally:
        if close_after and file_obj is not None:
            try:
                file_obj.close()
            except Exception:
                pass


# allow nicer import alias
__all__ = ['summarize_pdf']


def summarize_document_by_id(doc_id: int, db_config: Optional[Union[dict, object]] = None, timeout: Optional[float] = None, session: Optional[requests.Session] = None) -> dict:
    """
    Fetch the document file_path for `doc_id` from the `documents` table and upload the file to the AI endpoint.

    - doc_id: integer document id
    - db_config: either a dict with keys host,user,password,db,port OR an existing pymysql Connection object.
      If None, the function will attempt to read DB connection info from environment variables
      DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT.
    - timeout/session: forwarded to summarize_pdf call.

    Returns parsed JSON from the AI service.

    Raises ValueError if document not found or file missing. Raises ImportError if pymysql is not available.
    """
    if pymysql is None:
        raise ImportError('pymysql is required for summarize_document_by_id; please install it (pip install pymysql)')

    created_conn = False
    conn = None
    try:
        if db_config is None or isinstance(db_config, dict):
            cfg = db_config or {}
            host = cfg.get('host') or os.environ.get('DB_HOST', '127.0.0.1')
            user = cfg.get('user') or os.environ.get('DB_USER', 'root')
            password = cfg.get('password') or os.environ.get('DB_PASSWORD', '')
            dbname = cfg.get('db') or cfg.get('database') or os.environ.get('DB_NAME') or os.environ.get('DB_DATABASE')
            port = int(cfg.get('port') or os.environ.get('DB_PORT', 3306))
            if not dbname:
                raise ValueError('Database name not provided in db_config or environment (DB_NAME)')
            conn = pymysql.connect(host=host, user=user, password=password, database=dbname, port=port, cursorclass=DictCursor)
            created_conn = True
        else:
            # assume it's an existing connection-like object
            conn = db_config

        with conn.cursor() as cur:
            cur.execute("SELECT file_path FROM documents WHERE doc_id=%s", (doc_id,))
            row = cur.fetchone()
            if not row:
                raise ValueError(f'Document id {doc_id} not found')
            file_path = row.get('file_path') if isinstance(row, dict) else row[0]

        if not file_path:
            raise ValueError(f'No file_path recorded for doc_id {doc_id}')

        # If file_path is relative, resolve against project root (one level up from includes/)
        if not os.path.isabs(file_path):
            project_root = Path(__file__).resolve().parents[1]
            candidate = project_root / file_path
        else:
            candidate = Path(file_path)

        # Normalize and check existence
        candidate = candidate.resolve()
        if not candidate.exists():
            raise ValueError(f'File for doc_id {doc_id} not found on disk: {candidate}')

        # Open and forward to summarize_pdf
        with open(str(candidate), 'rb') as f:
            return summarize_pdf(f, timeout=timeout, session=session)

    finally:
        if created_conn and conn is not None:
            try:
                conn.close()
            except Exception:
                pass


__all__ = ['summarize_pdf', 'summarize_document_by_id']
