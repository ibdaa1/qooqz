# app/schemas/chat.py
"""
مخططات الدردشة - Pydantic
"""
from pydantic import BaseModel, Field
from typing import Optional, List


class ChatRequest(BaseModel):
    """طلب دردشة"""
    question: str = Field(..., min_length=1, max_length=2000, description="السؤال")
    thread_id: Optional[str] = Field(None, description="معرف المحادثة (اختياري)")
    language: Optional[str] = Field("ar", description="اللغة")


class SourceInfo(BaseModel):
    """معلومات المصدر"""
    chunk_id: Optional[str] = None
    content_preview: Optional[str] = None
    score: Optional[float] = 0.0


class ChatMetadata(BaseModel):
    """بيانات وصفية للرد"""
    model: str = ""
    input_tokens: int = 0
    output_tokens: int = 0
    latency_ms: int = 0
    sources_found: int = 0
    is_new_thread: bool = False
    has_image: bool = False
    has_memory: bool = False


class ChatResponse(BaseModel):
    """رد الدردشة"""
    thread_id: str
    message_id: str
    answer: str
    sources: List[SourceInfo] = []
    metadata: ChatMetadata = ChatMetadata()
    vision: Optional[dict] = None
