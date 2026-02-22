# app/api/v1/endpoints/knowledge.py
"""
نقاط نهاية قواعد المعرفة - تعمل مع mysql_conn.py مباشرة
"""
import uuid
import re
from fastapi import APIRouter, HTTPException
from app.db.mysql_conn import execute_query

router = APIRouter()


@router.get("/knowledge-bases")
def list_knowledge_bases():
    """قائمة قواعد المعرفة"""
    try:
        kbs = execute_query(
            "SELECT id, name, description, is_public, created_at FROM ai_knowledge_bases ORDER BY created_at DESC"
        ) or []
        for kb in kbs:
            if kb.get("created_at"):
                kb["created_at"] = str(kb["created_at"])
        return {"status": "ok", "knowledge_bases": kbs}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/knowledge-bases")
def create_knowledge_base(data: dict):
    """إنشاء قاعدة معرفة"""
    try:
        kb_id = str(uuid.uuid4())
        name = data.get("name", "بدون اسم")
        description = data.get("description", "")
        is_public = data.get("is_public", False)

        execute_query(
            "INSERT INTO ai_knowledge_bases (id, name, description, is_public) VALUES (%s, %s, %s, %s)",
            (kb_id, name, description, is_public)
        )
        return {"status": "ok", "knowledge_base_id": kb_id}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/knowledge-bases/{kb_id}/documents")
def add_document(kb_id: str, data: dict):
    """إضافة مستند وتقطيعه"""
    try:
        # تحقق من وجود قاعدة المعرفة
        existing = execute_query("SELECT id FROM ai_knowledge_bases WHERE id = %s", (kb_id,))
        if not existing:
            raise HTTPException(status_code=404, detail="قاعدة المعرفة غير موجودة")

        content = data.get("content", "")
        title = data.get("title", "مستند")
        language = data.get("language", "ar")

        if not content.strip():
            raise HTTPException(status_code=400, detail="المحتوى مطلوب")

        # إنشاء المستند
        doc_id = str(uuid.uuid4())
        execute_query(
            "INSERT INTO ai_documents (id, knowledge_base_id, title, language) VALUES (%s, %s, %s, %s)",
            (doc_id, kb_id, title, language)
        )

        # تقطيع النص
        chunks = simple_chunk_text(content, chunk_size=500, overlap=50)
        created = 0

        for idx, chunk_text in enumerate(chunks):
            chunk_id = str(uuid.uuid4())
            token_count = len(chunk_text.split())
            execute_query(
                """INSERT INTO ai_document_chunks
                   (id, document_id, chunk_index, content, language, token_count)
                   VALUES (%s, %s, %s, %s, %s, %s)""",
                (chunk_id, doc_id, idx + 1, chunk_text, language, token_count)
            )
            created += 1

        return {
            "status": "ok",
            "document_id": doc_id,
            "chunks_created": created,
            "message": f"تم إضافة المستند وإنشاء {created} قطعة",
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


def simple_chunk_text(text, chunk_size=500, overlap=50):
    """تقطيع النص البسيط"""
    if not text:
        return []

    # محاولة تقطيع بنمط سؤال/جواب
    qa_pattern = re.compile(r'(سؤال\s*[:：].*?جواب\s*[:：].*?)(?=سؤال\s*[:：]|$)', re.DOTALL)
    qa_matches = qa_pattern.findall(text)

    if qa_matches:
        return [m.strip() for m in qa_matches if m.strip()]

    # تقطيع عادي
    paragraphs = [p.strip() for p in text.split("\n\n") if p.strip()]

    if all(len(p) <= chunk_size for p in paragraphs) and paragraphs:
        return paragraphs

    chunks = []
    words = text.split()
    start = 0

    while start < len(words):
        end = min(start + chunk_size, len(words))
        chunk = " ".join(words[start:end])
        if chunk.strip():
            chunks.append(chunk.strip())
        start = end - overlap if end < len(words) else end

    return chunks if chunks else [text]
