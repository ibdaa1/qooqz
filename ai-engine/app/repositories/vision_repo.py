# app/repositories/vision_repo.py
"""
مستودع تحليل الصور (Vision / OCR)
"""
import uuid
import json
from app.db.session import execute_query


class VisionRepository:

    @staticmethod
    def create(file_id: str, extracted_text: str = None,
               description: str = None, structured_data: dict = None,
               message_id: str = None) -> str:
        """إنشاء سجل تحليل صورة"""
        vision_id = str(uuid.uuid4())
        execute_query(
            """INSERT INTO ai_vision_analyses 
               (id, file_id, message_id, extracted_text, description, structured_data)
               VALUES (%s, %s, %s, %s, %s, %s)""",
            (
                vision_id, file_id, message_id,
                extracted_text, description,
                json.dumps(structured_data or {}, ensure_ascii=False)
            ),
            fetch=False
        )
        return vision_id

    @staticmethod
    def get_by_file(file_id: str) -> dict:
        """جلب تحليل صورة بملف"""
        results = execute_query(
            "SELECT * FROM ai_vision_analyses WHERE file_id = %s ORDER BY created_at DESC LIMIT 1",
            (file_id,)
        )
        return results[0] if results else None

    @staticmethod
    def get_by_id(vision_id: str) -> dict:
        """جلب تحليل"""
        results = execute_query(
            "SELECT * FROM ai_vision_analyses WHERE id = %s",
            (vision_id,)
        )
        return results[0] if results else None

    @staticmethod
    def list_all(limit: int = 20) -> list:
        """جلب كل التحليلات"""
        return execute_query(
            "SELECT * FROM ai_vision_analyses ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []
