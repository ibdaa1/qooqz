# app/schemas/file.py
"""
مخططات الملفات - Pydantic
"""
from pydantic import BaseModel, Field
from typing import Optional


class FileUploadResponse(BaseModel):
    """رد رفع الملف"""
    file_id: str
    filename: str
    file_size: int
    mime_type: Optional[str] = None
    extracted_text_length: int = 0
    chunks_created: int = 0


class FileInfo(BaseModel):
    """معلومات الملف"""
    id: str
    filename: str
    mime_type: Optional[str] = None
    file_size: Optional[int] = None
    created_at: Optional[str] = None
