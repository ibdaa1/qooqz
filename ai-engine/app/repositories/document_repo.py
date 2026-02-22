# app/repositories/document_repo.py
"""
مستودع المستندات (Documents)
"""
import uuid
import json
from app.db.session import execute_query


class DocumentRepository:

    @staticmethod
    def create(knowledge_base_id: str, title: str = None,
               file_id: str = None, source_url: str = None,
               language: str = "ar", metadata: dict = None) -> str:
        """إنشاء مستند"""
        doc_id = str(uuid.uuid4())
        execute_query(
            """INSERT INTO ai_documents 
               (id, knowledge_base_id, file_id, title, source_url, language, metadata)
               VALUES (%s, %s, %s, %s, %s, %s, %s)""",
            (doc_id, knowledge_base_id, file_id, title, source_url,
             language, json.dumps(metadata or {}, ensure_ascii=False)),
            fetch=False
        )
        return doc_id

    @staticmethod
    def get_by_id(doc_id: str) -> dict:
        """جلب مستند"""
        results = execute_query(
            "SELECT * FROM ai_documents WHERE id = %s",
            (doc_id,)
        )
        return results[0] if results else None

    @staticmethod
    def get_by_knowledge_base(kb_id: str) -> list:
        """جلب مستندات قاعدة معرفة"""
        return execute_query(
            "SELECT * FROM ai_documents WHERE knowledge_base_id = %s ORDER BY created_at DESC",
            (kb_id,)
        ) or []

    @staticmethod
    def delete(doc_id: str):
        """حذف مستند"""
        execute_query(
            "DELETE FROM ai_documents WHERE id = %s",
            (doc_id,),
            fetch=False
        )

    @staticmethod
    def count() -> int:
        """عدد المستندات"""
        result = execute_query("SELECT COUNT(*) as total FROM ai_documents")
        return result[0]["total"] if result else 0
