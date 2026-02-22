# app/api/v1/endpoints/health.py
from fastapi import APIRouter
from app.db.mysql_conn import execute_query

router = APIRouter()

@router.get("/health")
def health():
    """
    نقطة نهاية للتحقق من صحة النظام وجلب عينة من الأسئلة
    """
    # استعلام مبسط بدون ORDER BY (لتجنب مشاكل الأعمدة)
    query = """
        SELECT id, content, language, token_count
        FROM ai_document_chunks 
        LIMIT 5
    """
    
    results = execute_query(query)
    
    # معلومات تشخيصية
    print(f"🔍 DB Query Results: {results}")
    print(f"🔍 Type: {type(results)}")
    
    sample_questions = []
    if results and isinstance(results, list):
        sample_questions = [
            {
                "id": row.get('id', 'N/A'),
                "content": row.get('content', ''),
                "language": row.get('language', 'ar'),
                "token_count": row.get('token_count', 0)
            }
            for row in results
        ]
    
    return {
        "status": "ok",
        "message": "API متصل بقاعدة البيانات بنجاح",
        "database_connection": True if results is not None else False,
        "total_chunks_found": len(sample_questions),
        "sample_chunks": sample_questions
    }


@router.get("/test-db")
def test_database():
    """
    اختبار شامل للاتصال بقاعدة البيانات
    """
    # 1. عدد السجلات الكلي
    count_query = "SELECT COUNT(*) as total FROM ai_document_chunks"
    count_result = execute_query(count_query)
    total_count = count_result[0]['total'] if count_result else 0
    
    # 2. أسماء الأعمدة
    columns_query = """
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'ai_document_chunks' 
        AND TABLE_SCHEMA = DATABASE()
    """
    columns_result = execute_query(columns_query)
    column_names = [col['COLUMN_NAME'] for col in columns_result] if columns_result else []
    
    # 3. عينة من البيانات
    sample_query = "SELECT * FROM ai_document_chunks LIMIT 3"
    sample_data = execute_query(sample_query)
    
    return {
        "status": "ok",
        "total_chunks_in_db": total_count,
        "table_columns": column_names,
        "sample_data": sample_data if sample_data else [],
        "connection_working": True
    }