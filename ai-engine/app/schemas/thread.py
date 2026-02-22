# app/schemas/thread.py
"""
مخططات المحادثات - Pydantic
"""
from pydantic import BaseModel, Field
from typing import Optional, List
from datetime import datetime


class ThreadCreate(BaseModel):
    """إنشاء محادثة"""
    title: Optional[str] = Field("محادثة جديدة", max_length=255)


class ThreadResponse(BaseModel):
    """رد المحادثة"""
    id: str
    title: Optional[str] = None
    created_at: Optional[str] = None
    updated_at: Optional[str] = None
    message_count: Optional[int] = 0


class MessageResponse(BaseModel):
    """رد الرسالة"""
    id: str
    thread_id: str
    role: str
    content: str
    model: Optional[str] = None
    tokens: Optional[int] = None
    latency_ms: Optional[int] = None
    language: Optional[str] = "ar"
    created_at: Optional[str] = None


class ThreadHistoryResponse(BaseModel):
    """تاريخ المحادثة"""
    thread_id: str
    title: Optional[str] = None
    messages: List[dict] = []
    message_count: int = 0
    memory_context: Optional[str] = None
