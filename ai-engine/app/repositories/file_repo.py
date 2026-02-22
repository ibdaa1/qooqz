# app/repositories/file_repo.py
"""
مستودع الملفات (Files)
"""
import uuid
import json
from app.db.session import execute_query


class FileRepository:

    @staticmethod
    def create(filename: str, mime_type: str = None, file_size: int = None,
               file_path: str = None, extracted_text: str = None,
               structured_data: dict = None, embedding_model: str = None) -> str:
        """إنشاء سجل ملف"""
        file_id = str(uuid.uuid4())
        execute_query(
            """INSERT INTO ai_files 
               (id, filename, mime_type, file_size, file_path, 
                extracted_text, structured_data, embedding_model)
               VALUES (%s, %s, %s, %s, %s, %s, %s, %s)""",
            (
                file_id, filename, mime_type, file_size, file_path,
                extracted_text,
                json.dumps(structured_data or {}, ensure_ascii=False),
                embedding_model
            ),
            fetch=False
        )
        return file_id

    @staticmethod
    def get_by_id(file_id: str) -> dict:
        """جلب ملف"""
        results = execute_query(
            "SELECT * FROM ai_files WHERE id = %s",
            (file_id,)
        )
        return results[0] if results else None

    @staticmethod
    def list_all(limit: int = 20) -> list:
        """جلب كل الملفات"""
        return execute_query(
            "SELECT id, filename, mime_type, file_size, created_at FROM ai_files ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []

    @staticmethod
    def update_extracted_text(file_id: str, text: str):
        """تحديث النص المستخرج"""
        execute_query(
            "UPDATE ai_files SET extracted_text = %s WHERE id = %s",
            (text, file_id),
            fetch=False
        )

    @staticmethod
    def delete(file_id: str):
        """حذف ملف"""
        execute_query(
            "DELETE FROM ai_files WHERE id = %s",
            (file_id,),
            fetch=False
        )

    @staticmethod
    def link_to_message(message_id: str, file_id: str):
        """ربط ملف برسالة"""
        execute_query(
            "INSERT IGNORE INTO ai_message_files (message_id, file_id) VALUES (%s, %s)",
            (message_id, file_id),
            fetch=False
        )
