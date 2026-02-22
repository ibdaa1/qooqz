# app/repositories/message_repo.py
"""
مستودع الرسائل (Messages)
"""
import uuid
import json
from app.db.session import execute_query


class MessageRepository:

    @staticmethod
    def create(thread_id: str, role: str, content: str, model: str = None,
               tokens: int = None, latency_ms: int = None,
               citations: list = None, tool_calls: list = None,
               language: str = "ar") -> str:
        """إنشاء رسالة جديدة"""
        message_id = str(uuid.uuid4())
        execute_query(
            """INSERT INTO ai_messages 
               (id, thread_id, role, content, model, tokens, latency_ms, 
                citations, tool_calls, language)
               VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)""",
            (
                message_id, thread_id, role, content, model,
                tokens, latency_ms,
                json.dumps(citations or [], ensure_ascii=False),
                json.dumps(tool_calls or [], ensure_ascii=False),
                language
            ),
            fetch=False
        )
        return message_id

    @staticmethod
    def get_by_id(message_id: str) -> dict:
        """جلب رسالة"""
        results = execute_query(
            "SELECT * FROM ai_messages WHERE id = %s",
            (message_id,)
        )
        return results[0] if results else None

    @staticmethod
    def get_thread_messages(thread_id: str, limit: int = 50) -> list:
        """جلب رسائل محادثة"""
        return execute_query(
            """SELECT * FROM ai_messages 
               WHERE thread_id = %s 
               ORDER BY created_at ASC 
               LIMIT %s""",
            (thread_id, limit)
        ) or []

    @staticmethod
    def get_recent_messages(thread_id: str, limit: int = 10) -> list:
        """جلب آخر رسائل المحادثة"""
        return execute_query(
            """SELECT * FROM ai_messages 
               WHERE thread_id = %s 
               ORDER BY created_at DESC 
               LIMIT %s""",
            (thread_id, limit)
        ) or []

    @staticmethod
    def count_thread_messages(thread_id: str) -> int:
        """عدد رسائل محادثة"""
        result = execute_query(
            "SELECT COUNT(*) as total FROM ai_messages WHERE thread_id = %s",
            (thread_id,)
        )
        return result[0]["total"] if result else 0

    @staticmethod
    def delete(message_id: str):
        """حذف رسالة"""
        execute_query(
            "DELETE FROM ai_messages WHERE id = %s",
            (message_id,),
            fetch=False
        )
