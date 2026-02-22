# app/repositories/memory_repo.py
"""
مستودع الذاكرة (Thread Memory)
"""
import json
from app.db.session import execute_query


class MemoryRepository:

    @staticmethod
    def get(thread_id: str) -> dict:
        """جلب ذاكرة محادثة"""
        results = execute_query(
            "SELECT * FROM ai_thread_memory WHERE thread_id = %s",
            (thread_id,)
        )
        if results and results[0]:
            row = results[0]
            if isinstance(row.get("key_facts"), str):
                try:
                    row["key_facts"] = json.loads(row["key_facts"])
                except (json.JSONDecodeError, TypeError):
                    row["key_facts"] = []
            return row
        return None

    @staticmethod
    def upsert(thread_id: str, summary: str, key_facts: list = None):
        """إنشاء أو تحديث ذاكرة المحادثة"""
        facts_json = json.dumps(key_facts or [], ensure_ascii=False)
        existing = MemoryRepository.get(thread_id)
        if existing:
            execute_query(
                """UPDATE ai_thread_memory 
                   SET summary = %s, key_facts = %s 
                   WHERE thread_id = %s""",
                (summary, facts_json, thread_id),
                fetch=False
            )
        else:
            execute_query(
                """INSERT INTO ai_thread_memory (thread_id, summary, key_facts) 
                   VALUES (%s, %s, %s)""",
                (thread_id, summary, facts_json),
                fetch=False
            )

    @staticmethod
    def delete(thread_id: str):
        """حذف ذاكرة المحادثة"""
        execute_query(
            "DELETE FROM ai_thread_memory WHERE thread_id = %s",
            (thread_id,),
            fetch=False
        )
