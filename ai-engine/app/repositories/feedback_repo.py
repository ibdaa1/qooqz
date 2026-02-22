# app/repositories/feedback_repo.py
"""
مستودع التقييمات (Feedback)
"""
import uuid
from app.db.session import execute_query


class FeedbackRepository:

    @staticmethod
    def create(message_id: str, rating: int, comment: str = None) -> str:
        """إنشاء تقييم"""
        feedback_id = str(uuid.uuid4())
        execute_query(
            "INSERT INTO ai_feedback (id, message_id, rating, comment) VALUES (%s, %s, %s, %s)",
            (feedback_id, message_id, rating, comment),
            fetch=False
        )
        return feedback_id

    @staticmethod
    def get_by_message(message_id: str) -> list:
        """جلب تقييمات رسالة"""
        return execute_query(
            "SELECT * FROM ai_feedback WHERE message_id = %s ORDER BY created_at DESC",
            (message_id,)
        ) or []

    @staticmethod
    def get_average_rating() -> float:
        """متوسط التقييمات"""
        result = execute_query("SELECT AVG(rating) as avg_rating FROM ai_feedback")
        return float(result[0]["avg_rating"]) if result and result[0]["avg_rating"] else 0.0

    @staticmethod
    def list_all(limit: int = 50) -> list:
        """جلب كل التقييمات"""
        return execute_query(
            "SELECT * FROM ai_feedback ORDER BY created_at DESC LIMIT %s",
            (limit,)
        ) or []
