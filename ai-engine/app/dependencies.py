# app/dependencies.py
"""
التبعيات المشتركة (Dependencies)
"""
from app.db.session import get_db


def get_database():
    """dependency لجلب اتصال قاعدة البيانات"""
    return get_db
