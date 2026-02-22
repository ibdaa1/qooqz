# app/schemas/knowledge.py
"""
مخططات قاعدة المعرفة - Pydantic
"""
from pydantic import BaseModel, Field
from typing import Optional, List


class KnowledgeBaseCreate(BaseModel):
    """إنشاء قاعدة معرفة"""
    name: str = Field(..., min_length=1, max_length=255)
    description: Optional[str] = None
    is_public: bool = False


class KnowledgeBaseResponse(BaseModel):
    """رد قاعدة المعرفة"""
    id: str
    name: str
    description: Optional[str] = None
    is_public: bool = False
    created_at: Optional[str] = None


class DocumentCreate(BaseModel):
    """إنشاء مستند"""
    knowledge_base_id: str
    title: Optional[str] = None
    content: str = Field(..., min_length=1)
    language: str = "ar"


class ChunkResponse(BaseModel):
    """رد القطعة"""
    id: str
    content: str
    language: Optional[str] = "ar"
    token_count: Optional[int] = 0
    score: Optional[float] = None
