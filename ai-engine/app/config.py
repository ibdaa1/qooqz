# app/config.py
"""
إعدادات النظام المركزية - يقرأ من .env
"""
import os
from dotenv import load_dotenv

load_dotenv()


class Settings:
    """إعدادات التطبيق"""

    # قاعدة البيانات
    DB_HOST: str = os.getenv("DB_HOST", "localhost")
    DB_NAME: str = os.getenv("DB_NAME", "")
    DB_USER: str = os.getenv("DB_USER", "")
    DB_PASS: str = os.getenv("DB_PASS", "")
    DB_CHARSET: str = os.getenv("DB_CHARSET", "utf8mb4")
    DB_POOL_SIZE: int = int(os.getenv("DB_POOL_SIZE", "5"))

    # التطبيق
    DEBUG: bool = os.getenv("DEBUG", "false").lower() == "true"
    APP_ENV: str = os.getenv("APP_ENV", "production")
    API_VERSION: str = os.getenv("API_VERSION", "v1")

    # الأمان
    JWT_SECRET: str = os.getenv("JWT_SECRET", "change-me-in-production")
    API_KEY: str = os.getenv("API_KEY", "")

    # الملفات
    UPLOAD_DIR: str = os.getenv("UPLOAD_DIR", "uploads")
    MAX_FILE_SIZE: int = int(os.getenv("MAX_FILE_SIZE", str(50 * 1024 * 1024)))  # 50MB
    ALLOWED_IMAGE_TYPES: list = ["image/jpeg", "image/png", "image/gif", "image/webp"]
    ALLOWED_DOC_TYPES: list = ["application/pdf", "text/plain",
                                "application/vnd.openxmlformats-officedocument.wordprocessingml.document"]

    # RAG إعدادات
    CHUNK_SIZE: int = int(os.getenv("CHUNK_SIZE", "500"))
    CHUNK_OVERLAP: int = int(os.getenv("CHUNK_OVERLAP", "50"))
    TOP_K_RESULTS: int = int(os.getenv("TOP_K_RESULTS", "5"))
    MIN_RELEVANCE_SCORE: float = float(os.getenv("MIN_RELEVANCE_SCORE", "0.1"))

    # الذاكرة
    MAX_MEMORY_MESSAGES: int = int(os.getenv("MAX_MEMORY_MESSAGES", "20"))
    MEMORY_SUMMARY_THRESHOLD: int = int(os.getenv("MEMORY_SUMMARY_THRESHOLD", "10"))

    # اللغة
    DEFAULT_LANGUAGE: str = os.getenv("DEFAULT_LANGUAGE", "ar")

    # المنفذ
    PORT: int = int(os.getenv("PORT", "8888"))


settings = Settings()
