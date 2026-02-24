# app/api/v1/endpoints/files.py
"""
نقاط نهاية الملفات - محسّنة مع استخراج النص
"""
import os
import uuid
from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from typing import Optional
from app.db.mysql_conn import execute_query
from app.utils.file_processor import extract_text_from_file

router = APIRouter()

UPLOAD_DIR = os.environ.get("UPLOAD_DIR", "uploads")


@router.post("/files/upload")
async def upload_file(
    file: UploadFile = File(...),
    knowledge_base_id: Optional[str] = Form(None),
):
    """رفع ملف واستخراج نصه تلقائياً"""
    try:
        content = await file.read()
        os.makedirs(UPLOAD_DIR, exist_ok=True)

        file_ext = os.path.splitext(file.filename)[1].lower() or ".bin"
        safe_name = f"{uuid.uuid4()}{file_ext}"
        file_path = os.path.join(UPLOAD_DIR, safe_name)

        with open(file_path, "wb") as f:
            f.write(content)

        file_id = str(uuid.uuid4())
        
        # استخراج النص باستخدام المعالج الجديد
        processed = extract_text_from_file(file_path, file.content_type, content)
        extracted_text = processed.get("text", "")
        
        # حفظ في قاعدة البيانات
        execute_query(
            """INSERT INTO ai_files (id, filename, mime_type, file_size, file_path, extracted_text)
               VALUES (%s, %s, %s, %s, %s, %s)""",
            (file_id, file.filename, file.content_type, len(content), file_path, extracted_text)
        )
        
        # إذا تم تحديد قاعدة معرفة، قم بإضافة الملف إليها كمستند
        if knowledge_base_id:
             doc_id = str(uuid.uuid4())
             execute_query(
                 "INSERT INTO ai_documents (id, knowledge_base_id, file_id, title, language) VALUES (%s, %s, %s, %s, %s)",
                 (doc_id, knowledge_base_id, file_id, file.filename, "ar")
             )
             # يمكن هنا إضافة تقطيع تلقائي أيضاً

        return {
            "status": "ok",
            "file_id": file_id,
            "filename": file.filename,
            "file_size": len(content),
            "mime_type": file.content_type,
            "text_extracted": bool(extracted_text),
            "extracted_text": extracted_text,
            "preview": extracted_text[:100] if extracted_text else ""
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/files")
def list_files(limit: int = 20):
    """قائمة الملفات"""
    try:
        files = execute_query(
            "SELECT id, filename, mime_type, file_size, created_at FROM ai_files ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []
        for f in files:
            if f.get("created_at"):
                f["created_at"] = str(f["created_at"])
        return {"status": "ok", "files": files}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
