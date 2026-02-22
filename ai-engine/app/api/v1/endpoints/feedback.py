# app/api/v1/endpoints/feedback.py
"""
نقاط نهاية التقييمات - تعمل مع mysql_conn.py مباشرة
"""
import uuid
from fastapi import APIRouter, HTTPException
from app.db.mysql_conn import execute_query

router = APIRouter()


@router.post("/feedback")
def submit_feedback(data: dict):
    """إرسال تقييم"""
    try:
        message_id = data.get("message_id")
        rating = data.get("rating")
        comment = data.get("comment", "")

        if not message_id or not rating:
            raise HTTPException(status_code=400, detail="message_id و rating مطلوبان")

        feedback_id = str(uuid.uuid4())
        execute_query(
            "INSERT INTO ai_feedback (id, message_id, rating, comment) VALUES (%s, %s, %s, %s)",
            (feedback_id, message_id, rating, comment)
        )
        return {"status": "ok", "feedback_id": feedback_id, "message": "شكراً لتقييمك!"}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/feedback")
def list_feedback(limit: int = 50):
    """قائمة التقييمات"""
    try:
        feedbacks = execute_query(
            "SELECT id, message_id, rating, comment, created_at FROM ai_feedback ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []

        for fb in feedbacks:
            if fb.get("created_at"):
                fb["created_at"] = str(fb["created_at"])

        avg_result = execute_query("SELECT AVG(rating) as avg_rating FROM ai_feedback")
        avg = round(avg_result[0]["avg_rating"] or 0, 2) if avg_result else 0

        return {"status": "ok", "feedbacks": feedbacks, "average_rating": avg}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
