# app/db/base.py
"""
قاعدة البيانات - إدارة تجمع الاتصالات (Connection Pool)
"""
import mysql.connector
from mysql.connector import pooling, Error
from app.config import settings
from app.core.logging_config import logger

_pool = None


def init_pool():
    """إنشاء تجمع اتصالات"""
    global _pool
    try:
        _pool = pooling.MySQLConnectionPool(
            pool_name="ai_engine_pool",
            pool_size=settings.DB_POOL_SIZE,
            pool_reset_session=True,
            host=settings.DB_HOST,
            user=settings.DB_USER,
            password=settings.DB_PASS,
            database=settings.DB_NAME,
            charset=settings.DB_CHARSET,
            collation="utf8mb4_unicode_ci",
            autocommit=False,
        )
        logger.info(f"✅ تم إنشاء تجمع الاتصالات بنجاح ({settings.DB_POOL_SIZE} اتصالات)")
        return True
    except Error as e:
        logger.error(f"❌ فشل إنشاء تجمع الاتصالات: {e}")
        return False


def get_pool_connection():
    """الحصول على اتصال من التجمع"""
    global _pool
    if _pool is None:
        init_pool()
    try:
        conn = _pool.get_connection()
        return conn
    except Error as e:
        logger.error(f"❌ فشل الحصول على اتصال من التجمع: {e}")
        # fallback إلى اتصال مباشر
        try:
            conn = mysql.connector.connect(
                host=settings.DB_HOST,
                user=settings.DB_USER,
                password=settings.DB_PASS,
                database=settings.DB_NAME,
                charset=settings.DB_CHARSET,
            )
            return conn
        except Error as e2:
            logger.error(f"❌ فشل الاتصال المباشر أيضاً: {e2}")
            return None


def close_pool():
    """إغلاق تجمع الاتصالات"""
    global _pool
    # mysql-connector-python لا يحتوي على close() للـ pool
    _pool = None
    logger.info("🔒 تم إغلاق تجمع الاتصالات")
