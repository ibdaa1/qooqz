# app/core/security.py
"""
أمان النظام - التحقق من API Key
"""
from fastapi import Header, HTTPException, status
from app.config import settings


async def verify_api_key(x_api_key: str = Header(default=None)):
    """التحقق من مفتاح API (اختياري إذا لم يتم إعداده)"""
    if not settings.API_KEY:
        return True
    if x_api_key != settings.API_KEY:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="مفتاح API غير صالح"
        )
    return True
