# app/exceptions.py
"""
استثناءات مخصصة للنظام
"""
from fastapi import HTTPException, status


class NotFoundError(HTTPException):
    """العنصر غير موجود"""
    def __init__(self, detail: str = "العنصر المطلوب غير موجود"):
        super().__init__(status_code=status.HTTP_404_NOT_FOUND, detail=detail)


class DatabaseError(HTTPException):
    """خطأ في قاعدة البيانات"""
    def __init__(self, detail: str = "خطأ في قاعدة البيانات"):
        super().__init__(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=detail)


class ValidationError(HTTPException):
    """خطأ في التحقق من البيانات"""
    def __init__(self, detail: str = "بيانات غير صالحة"):
        super().__init__(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=detail)


class FileProcessingError(HTTPException):
    """خطأ في معالجة الملف"""
    def __init__(self, detail: str = "خطأ في معالجة الملف"):
        super().__init__(status_code=status.HTTP_400_BAD_REQUEST, detail=detail)


class ThreadNotFoundError(NotFoundError):
    """المحادثة غير موجودة"""
    def __init__(self, thread_id: str = ""):
        detail = f"المحادثة غير موجودة: {thread_id}" if thread_id else "المحادثة غير موجودة"
        super().__init__(detail=detail)


class MessageNotFoundError(NotFoundError):
    """الرسالة غير موجودة"""
    def __init__(self, message_id: str = ""):
        detail = f"الرسالة غير موجودة: {message_id}" if message_id else "الرسالة غير موجودة"
        super().__init__(detail=detail)
