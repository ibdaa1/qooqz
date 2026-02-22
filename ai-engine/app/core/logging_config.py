# app/core/logging_config.py
"""
إعداد نظام السجلات (Logging)
"""
import logging
import os
from logging.handlers import RotatingFileHandler

LOG_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "logs")
os.makedirs(LOG_DIR, exist_ok=True)


def setup_logging(level: str = "INFO") -> logging.Logger:
    """إعداد نظام السجلات"""
    logger = logging.getLogger("ai_engine")
    logger.setLevel(getattr(logging, level.upper(), logging.INFO))

    # تنسيق السجلات
    formatter = logging.Formatter(
        "[%(asctime)s] %(levelname)s - %(name)s - %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S"
    )

    # سجل الملف
    file_handler = RotatingFileHandler(
        os.path.join(LOG_DIR, "ai_engine.log"),
        maxBytes=5 * 1024 * 1024,  # 5MB
        backupCount=3,
        encoding="utf-8"
    )
    file_handler.setFormatter(formatter)
    logger.addHandler(file_handler)

    # سجل الشاشة
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)

    return logger


# إنشاء logger عام
logger = setup_logging()
