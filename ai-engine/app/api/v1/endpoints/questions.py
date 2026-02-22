# app/api/v1/endpoints/questions.py
from fastapi import APIRouter
from app.db.mysql_conn import execute_query

router = APIRouter()

@router.get("/questions")
def get_questions():
    query = "SELECT content FROM ai_document_chunks ORDER BY created_at LIMIT 25"
    results = execute_query(query)
    if results is None or len(results) == 0:
        return {"status": "ok", "questions": [], "message": "لا توجد أسئلة في قاعدة البيانات."}
    
    questions = [row["content"] for row in results]
    return {"status": "ok", "questions": questions}
