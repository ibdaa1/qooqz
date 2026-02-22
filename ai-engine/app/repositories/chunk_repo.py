# app/repositories/chunk_repo.py
"""
مستودع قطع المستندات (Document Chunks)
"""
import uuid
import json
from app.db.session import execute_query, execute_many


class ChunkRepository:

    @staticmethod
    def create(document_id: str, chunk_index: int, content: str,
               language: str = "ar", token_count: int = None,
               metadata: dict = None) -> str:
        """إنشاء قطعة جديدة"""
        chunk_id = str(uuid.uuid4())
        meta_json = json.dumps(metadata or {}, ensure_ascii=False)
        execute_query(
            """INSERT INTO ai_document_chunks 
               (id, document_id, chunk_index, content, language, token_count, metadata)
               VALUES (%s, %s, %s, %s, %s, %s, %s)""",
            (chunk_id, document_id, chunk_index, content, language, token_count, meta_json),
            fetch=False
        )
        return chunk_id

    @staticmethod
    def bulk_create(chunks: list):
        """إنشاء عدة قطع دفعة واحدة"""
        query = """INSERT INTO ai_document_chunks 
                   (id, document_id, chunk_index, content, language, token_count, metadata)
                   VALUES (%s, %s, %s, %s, %s, %s, %s)"""
        data = [
            (
                str(uuid.uuid4()),
                c["document_id"], c["chunk_index"], c["content"],
                c.get("language", "ar"), c.get("token_count", 0),
                json.dumps(c.get("metadata", {}), ensure_ascii=False)
            )
            for c in chunks
        ]
        return execute_many(query, data)

    @staticmethod
    def search_by_content(query_text: str, limit: int = 10) -> list:
        """بحث في محتوى القطع"""
        return execute_query(
            """SELECT id, document_id, chunk_index, content, language, token_count
               FROM ai_document_chunks 
               WHERE content LIKE %s
               LIMIT %s""",
            (f"%{query_text}%", limit)
        ) or []

    @staticmethod
    def get_all(limit: int = 100) -> list:
        """جلب كل القطع"""
        return execute_query(
            """SELECT id, document_id, chunk_index, content, language, token_count
               FROM ai_document_chunks 
               ORDER BY created_at DESC
               LIMIT %s""",
            (limit,)
        ) or []

    @staticmethod
    def get_by_document(document_id: str) -> list:
        """جلب قطع مستند"""
        return execute_query(
            """SELECT * FROM ai_document_chunks 
               WHERE document_id = %s 
               ORDER BY chunk_index ASC""",
            (document_id,)
        ) or []

    @staticmethod
    def get_by_id(chunk_id: str) -> dict:
        """جلب قطعة"""
        results = execute_query(
            "SELECT * FROM ai_document_chunks WHERE id = %s",
            (chunk_id,)
        )
        return results[0] if results else None

    @staticmethod
    def delete_by_document(document_id: str):
        """حذف كل قطع مستند"""
        execute_query(
            "DELETE FROM ai_document_chunks WHERE document_id = %s",
            (document_id,),
            fetch=False
        )

    @staticmethod
    def count() -> int:
        """عدد القطع الكلي"""
        result = execute_query("SELECT COUNT(*) as total FROM ai_document_chunks")
        return result[0]["total"] if result else 0

    @staticmethod
    def fulltext_search(keywords: list, limit: int = 10) -> list:
        """بحث بكلمات متعددة"""
        if not keywords:
            return []
        conditions = " OR ".join(["content LIKE %s" for _ in keywords])
        params = tuple(f"%{kw}%" for kw in keywords)
        params += (limit,)
        return execute_query(
            f"""SELECT id, document_id, chunk_index, content, language, token_count
                FROM ai_document_chunks 
                WHERE {conditions}
                LIMIT %s""",
            params
        ) or []
