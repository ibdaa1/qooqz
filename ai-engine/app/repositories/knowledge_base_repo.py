# app/repositories/knowledge_base_repo.py
"""
مستودع قواعد المعرفة (Knowledge Bases)
"""
import uuid
import json
from app.db.session import execute_query


class KnowledgeBaseRepository:

    @staticmethod
    def create(name: str, description: str = None,
               is_public: bool = False, metadata: dict = None) -> str:
        """إنشاء قاعدة معرفة"""
        kb_id = str(uuid.uuid4())
        execute_query(
            """INSERT INTO ai_knowledge_bases (id, name, description, is_public, metadata)
               VALUES (%s, %s, %s, %s, %s)""",
            (kb_id, name, description, 1 if is_public else 0,
             json.dumps(metadata or {}, ensure_ascii=False)),
            fetch=False
        )
        return kb_id

    @staticmethod
    def get_by_id(kb_id: str) -> dict:
        """جلب قاعدة معرفة"""
        results = execute_query(
            "SELECT * FROM ai_knowledge_bases WHERE id = %s",
            (kb_id,)
        )
        return results[0] if results else None

    @staticmethod
    def list_all(limit: int = 20) -> list:
        """جلب كل قواعد المعرفة"""
        return execute_query(
            "SELECT * FROM ai_knowledge_bases ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []

    @staticmethod
    def update(kb_id: str, name: str = None, description: str = None):
        """تحديث قاعدة معرفة"""
        updates = []
        params = []
        if name:
            updates.append("name = %s")
            params.append(name)
        if description is not None:
            updates.append("description = %s")
            params.append(description)
        if updates:
            params.append(kb_id)
            execute_query(
                f"UPDATE ai_knowledge_bases SET {', '.join(updates)} WHERE id = %s",
                tuple(params),
                fetch=False
            )

    @staticmethod
    def delete(kb_id: str):
        """حذف قاعدة معرفة"""
        execute_query(
            "DELETE FROM ai_knowledge_bases WHERE id = %s",
            (kb_id,),
            fetch=False
        )
