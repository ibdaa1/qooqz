# app/db/mysql_conn.py
import mysql.connector
from mysql.connector import Error
import os
from dotenv import load_dotenv

# تحميل إعدادات البيئة من .env
load_dotenv()

DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASS")
DB_NAME = os.getenv("DB_NAME")
DB_CHARSET = os.getenv("DB_CHARSET", "utf8mb4")

def get_connection():
    """إنشاء اتصال بقاعدة البيانات"""
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        if conn.is_connected():
            print(f"✅ اتصال ناجح بقاعدة البيانات: {DB_NAME}")
            return conn
    except Error as e:
        print(f"❌ خطأ في الاتصال بقاعدة البيانات: {e}")
        return None

def execute_query(query, params=None):
    """تنفيذ أي استعلام (SELECT أو INSERT/UPDATE/DELETE)"""
    conn = get_connection()
    if not conn:
        print("❌ فشل الحصول على اتصال قاعدة البيانات")
        return None
    
    try:
        cursor = conn.cursor(dictionary=True)
        
        print(f"🔍 تنفيذ الاستعلام: {query[:100]}...")
        
        if params:
            cursor.execute(query, params)
        else:
            cursor.execute(query)
        
        # إذا كان SELECT
        if query.strip().lower().startswith("select"):
            result = cursor.fetchall()
            print(f"✅ تم جلب {len(result)} سجل")
        else:
            conn.commit()
            result = cursor.rowcount
            print(f"✅ تم تعديل {result} سجل")
        
        cursor.close()
        return result
        
    except Error as e:
        print(f"❌ خطأ في تنفيذ الاستعلام: {e}")
        print(f"📝 الاستعلام: {query}")
        if params:
            print(f"📝 المعاملات: {params}")
        return None
        
    finally:
        if conn and conn.is_connected():
            conn.close()
            print("🔒 تم إغلاق الاتصال")