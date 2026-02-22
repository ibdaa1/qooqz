# app/schemas/feedback.py
"""
مخططات التقييمات - Pydantic
"""
from pydantic import BaseModel, Field
from typing import Optional


class FeedbackCreate(BaseModel):
    """إنشاء تقييم"""
    message_id: str
    rating: int = Field(..., ge=1, le=5, description="التقييم من 1 إلى 5")
    comment: Optional[str] = None


class FeedbackResponse(BaseModel):
    """رد التقييم"""
    id: str
    message_id: str
    rating: int
    comment: Optional[str] = None
    created_at: Optional[str] = None
