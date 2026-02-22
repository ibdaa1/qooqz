#!/usr/bin/env python3
"""
test_database_direct.py
اختبار مباشر للاتصال بقاعدة البيانات بدون FastAPI
"""

import mysql.connector
from mysql.connector import Error
import os
from dotenv import load_dotenv
import json

# تحميل إعدادات البيئة
load_dotenv()

DB_HOST = os.getenv("DB_HOST", "localhost")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASS")
DB_NAME = os.getenv("DB_NAME")
DB_CHARSET = os.getenv("DB_CHARSET", "utf8mb4")

def print_section(title):
    """طباعة عنوان قسم"""
    print("\n" + "="*60)
    print(f"  {title}")
    print("="*60)

def test_connection():
    """اختبار الاتصال بقاعدة البيانات"""
    print_section("1️⃣ اختبار الاتصال")
    
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        
        if conn.is_connected():
            db_info = conn.get_server_info()
            print(f"✅ اتصال ناجح!")
            print(f"   📌 Server: {DB_HOST}")
            print(f"   📌 Database: {DB_NAME}")
            print(f"   📌 MySQL Version: {db_info}")
            conn.close()
            return True
        else:
            print("❌ فشل الاتصال")
            return False
            
    except Error as e:
        print(f"❌ خطأ في الاتصال: {e}")
        return False

def get_table_info():
    """الحصول على معلومات الجدول"""
    print_section("2️⃣ معلومات الجدول")
    
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        
        cursor = conn.cursor(dictionary=True)
        
        # عدد السجلات
        cursor.execute("SELECT COUNT(*) as total FROM ai_document_chunks")
        count = cursor.fetchone()
        print(f"📊 إجمالي السجلات: {count['total']}")
        
        # معلومات الأعمدة
        cursor.execute("""
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = 'ai_document_chunks' 
            AND TABLE_SCHEMA = DATABASE()
            ORDER BY ORDINAL_POSITION
        """)
        
        columns = cursor.fetchall()
        print(f"\n📋 الأعمدة ({len(columns)}):")
        for col in columns:
            nullable = "NULL" if col['IS_NULLABLE'] == 'YES' else "NOT NULL"
            default = f"DEFAULT: {col['COLUMN_DEFAULT']}" if col['COLUMN_DEFAULT'] else ""
            print(f"   • {col['COLUMN_NAME']:20s} {col['DATA_TYPE']:15s} {nullable:10s} {default}")
        
        cursor.close()
        conn.close()
        
    except Error as e:
        print(f"❌ خطأ: {e}")

def test_queries():
    """اختبار استعلامات مختلفة"""
    print_section("3️⃣ اختبار الاستعلامات")
    
    queries = [
        ("استعلام بسيط (بدون ORDER BY)", 
         "SELECT id, content, language FROM ai_document_chunks LIMIT 3"),
        
        ("استعلام مع ORDER BY id", 
         "SELECT id, content FROM ai_document_chunks ORDER BY id ASC LIMIT 3"),
        
        ("فحص created_at NULL", 
         "SELECT COUNT(*) as count FROM ai_document_chunks WHERE created_at IS NULL"),
        
        ("استعلام مع created_at NOT NULL",
         "SELECT id, content, created_at FROM ai_document_chunks WHERE created_at IS NOT NULL LIMIT 3"),
    ]
    
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        
        cursor = conn.cursor(dictionary=True)
        
        for idx, (desc, query) in enumerate(queries, 1):
            print(f"\n{idx}. {desc}")
            print(f"   SQL: {query[:70]}...")
            
            try:
                cursor.execute(query)
                results = cursor.fetchall()
                
                if results:
                    print(f"   ✅ نجح! عدد النتائج: {len(results)}")
                    
                    # عرض أول نتيجة
                    if len(results) > 0:
                        first = results[0]
                        print(f"   📄 أول نتيجة:")
                        for key, value in first.items():
                            if key == 'content':
                                value_display = str(value)[:50] + "..." if len(str(value)) > 50 else value
                            else:
                                value_display = value
                            print(f"      • {key}: {value_display}")
                else:
                    print(f"   ⚠️ لا توجد نتائج")
                    
            except Error as e:
                print(f"   ❌ فشل: {e}")
        
        cursor.close()
        conn.close()
        
    except Error as e:
        print(f"❌ خطأ عام: {e}")

def test_specific_problem():
    """اختبار المشكلة المحددة في الكود الأصلي"""
    print_section("4️⃣ اختبار المشكلة الأصلية")
    
    # الاستعلام الأصلي الذي كان يسبب المشكلة
    original_query = """
        SELECT content 
        FROM ai_document_chunks 
        ORDER BY created_at ASC
        LIMIT 5
    """
    
    print("🔍 الاستعلام الأصلي:")
    print(f"   {original_query.strip()}")
    
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        
        cursor = conn.cursor(dictionary=True)
        cursor.execute(original_query)
        results = cursor.fetchall()
        
        if results:
            print(f"\n✅ الاستعلام نجح! عدد النتائج: {len(results)}")
            print("\n📝 النتائج:")
            for idx, row in enumerate(results, 1):
                content = row['content'][:80] + "..." if len(row['content']) > 80 else row['content']
                print(f"   {idx}. {content}")
        else:
            print("\n❌ الاستعلام لم يُرجع أي نتائج!")
            print("   السبب المحتمل: جميع قيم created_at هي NULL")
        
        cursor.close()
        conn.close()
        
    except Error as e:
        print(f"\n❌ الاستعلام فشل!")
        print(f"   الخطأ: {e}")
        print("\n💡 الحل المقترح:")
        print("   1. استخدم ORDER BY id بدلاً من ORDER BY created_at")
        print("   2. أو أضف WHERE created_at IS NOT NULL")
        print("   3. أو قم بتحديث قيم created_at الـ NULL")

def suggest_fix():
    """اقتراح الحل"""
    print_section("5️⃣ الحلول المقترحة")
    
    print("""
✅ الحل #1: استخدام ORDER BY id (موصى به)
   query = "SELECT content FROM ai_document_chunks ORDER BY id ASC LIMIT 5"

✅ الحل #2: إزالة ORDER BY تماماً
   query = "SELECT content FROM ai_document_chunks LIMIT 5"

✅ الحل #3: تحديث قيم created_at
   UPDATE ai_document_chunks SET created_at = NOW() WHERE created_at IS NULL;

✅ الحل #4: استخدام COALESCE
   query = "SELECT content FROM ai_document_chunks 
            ORDER BY COALESCE(created_at, '1970-01-01') ASC LIMIT 5"

💡 للتطبيق:
   1. افتح ملف: app/api/v1/endpoints/health.py
   2. استبدل الاستعلام بأحد الحلول أعلاه
   3. أعد تشغيل FastAPI: uvicorn main:app --reload
    """)

def export_sample_data():
    """تصدير عينة من البيانات"""
    print_section("6️⃣ تصدير عينة البيانات")
    
    try:
        conn = mysql.connector.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            charset=DB_CHARSET
        )
        
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM ai_document_chunks LIMIT 5")
        results = cursor.fetchall()
        
        if results:
            filename = "sample_data.json"
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(results, f, ensure_ascii=False, indent=2, default=str)
            
            print(f"✅ تم تصدير {len(results)} سجل إلى: {filename}")
            print("\nمحتوى الملف:")
            print(json.dumps(results, ensure_ascii=False, indent=2, default=str)[:500] + "...")
        else:
            print("⚠️ لا توجد بيانات للتصدير")
        
        cursor.close()
        conn.close()
        
    except Error as e:
        print(f"❌ خطأ: {e}")

def main():
    """الدالة الرئيسية"""
    print("\n" + "🔧"*30)
    print("  🔍 اختبار شامل لقاعدة البيانات - نظام RAG")
    print("🔧"*30)
    
    # التحقق من متغيرات البيئة
    if not all([DB_USER, DB_PASSWORD, DB_NAME]):
        print("\n❌ خطأ: متغيرات البيئة غير مكتملة!")
        print("   تأكد من وجود ملف .env يحتوي على:")
        print("   - DB_HOST")
        print("   - DB_USER")
        print("   - DB_PASS")
        print("   - DB_NAME")
        return
    
    # تشغيل جميع الاختبارات
    if test_connection():
        get_table_info()
        test_queries()
        test_specific_problem()
        suggest_fix()
        export_sample_data()
    
    print("\n" + "✅"*30)
    print("  اكتمل الاختبار!")
    print("✅"*30 + "\n")

if __name__ == "__main__":
    main()