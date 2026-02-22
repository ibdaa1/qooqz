# app/repositories/usage_repo.py
"""
مستودع سجلات الاستخدام (Usage Logs)
"""
from app.db.session import execute_query


class UsageRepository:

    @staticmethod
    def log(thread_id: str, model: str, tokens_input: int = 0,
            tokens_output: int = 0, cost_usd: float = 0.0):
        """تسجيل استخدام"""
        execute_query(
            """INSERT INTO ai_usage_logs 
               (thread_id, model, tokens_input, tokens_output, cost_usd)
               VALUES (%s, %s, %s, %s, %s)""",
            (thread_id, model, tokens_input, tokens_output, cost_usd),
            fetch=False
        )

    @staticmethod
    def get_thread_usage(thread_id: str) -> list:
        """جلب استخدام محادثة"""
        return execute_query(
            "SELECT * FROM ai_usage_logs WHERE thread_id = %s ORDER BY created_at DESC",
            (thread_id,)
        ) or []

    @staticmethod
    def get_total_usage() -> dict:
        """إجمالي الاستخدام"""
        result = execute_query(
            """SELECT 
                 COUNT(*) as total_requests,
                 COALESCE(SUM(tokens_input), 0) as total_input_tokens,
                 COALESCE(SUM(tokens_output), 0) as total_output_tokens,
                 COALESCE(SUM(cost_usd), 0) as total_cost
               FROM ai_usage_logs"""
        )
        return result[0] if result else {
            "total_requests": 0,
            "total_input_tokens": 0,
            "total_output_tokens": 0,
            "total_cost": 0
        }
