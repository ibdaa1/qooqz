# app/repositories/thread_repo.py
"""
مستودع المحادثات (Threads)
"""
import uuid
import json
from app.db.session import execute_query


class ThreadRepository:

    @staticmethod
    def create(title: str = None, metadata: dict = None) -> str:
        """إنشاء محادثة جديدة"""
        thread_id = str(uuid.uuid4())
        meta_json = json.dumps(metadata or {}, ensure_ascii=False)
        execute_query(
            "INSERT INTO ai_threads (id, title, metadata) VALUES (%s, %s, %s)",
            (thread_id, title or "محادثة جديدة", meta_json),
            fetch=False
        )
        return thread_id

    @staticmethod
    def get_by_id(thread_id: str) -> dict:
        """جلب محادثة بالمعرف"""
        results = execute_query(
            "SELECT * FROM ai_threads WHERE id = %s",
            (thread_id,)
        )
        return results[0] if results else None

    @staticmethod
    def list_all(limit: int = 20, offset: int = 0) -> list:
        """جلب كل المحادثات"""
        return execute_query(
            "SELECT * FROM ai_threads ORDER BY updated_at DESC LIMIT %s OFFSET %s",
            (limit, offset)
        ) or []

    @staticmethod
    def update_title(thread_id: str, title: str):
        """تحديث عنوان المحادثة"""
        execute_query(
            "UPDATE ai_threads SET title = %s WHERE id = %s",
            (title, thread_id),
            fetch=False
        )

    @staticmethod
    def delete(thread_id: str):
        """حذف محادثة"""
        execute_query(
            "DELETE FROM ai_threads WHERE id = %s",
            (thread_id,),
            fetch=False
        )

    @staticmethod
    def count() -> int:
        """عدد المحادثات"""
        result = execute_query("SELECT COUNT(*) as total FROM ai_threads")
        return result[0]["total"] if result else 0
