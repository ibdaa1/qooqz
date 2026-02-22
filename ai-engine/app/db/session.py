# app/db/session.py
"""
إدارة الجلسات - Context Manager للاستعلامات
"""
from contextlib import contextmanager
from mysql.connector import Error
from app.db.base import get_pool_connection
from app.core.logging_config import logger


@contextmanager
def get_db():
    """Context manager للحصول على اتصال مع auto-commit/rollback"""
    conn = get_pool_connection()
    if conn is None:
        raise Exception("فشل الاتصال بقاعدة البيانات")
    try:
        yield conn
        conn.commit()
    except Error as e:
        conn.rollback()
        logger.error(f"❌ خطأ DB - تم التراجع: {e}")
        raise
    finally:
        try:
            conn.close()
        except Exception:
            pass


def execute_query(query: str, params: tuple = None, fetch: bool = True):
    """تنفيذ استعلام واحد مع إدارة الاتصال"""
    with get_db() as conn:
        cursor = conn.cursor(dictionary=True)
        try:
            if params:
                cursor.execute(query, params)
            else:
                cursor.execute(query)

            if fetch and query.strip().upper().startswith("SELECT"):
                return cursor.fetchall()
            else:
                conn.commit()
                return cursor.lastrowid if cursor.lastrowid else cursor.rowcount
        finally:
            cursor.close()


def execute_many(query: str, data_list: list):
    """تنفيذ استعلام متعدد (INSERT باتش)"""
    with get_db() as conn:
        cursor = conn.cursor()
        try:
            cursor.executemany(query, data_list)
            conn.commit()
            return cursor.rowcount
        finally:
            cursor.close()
