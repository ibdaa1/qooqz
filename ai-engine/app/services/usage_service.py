# app/services/usage_service.py
"""
خدمة تسجيل الاستخدام
"""
from app.repositories.usage_repo import UsageRepository
from app.core.constants import AI_MODEL_NAME
from app.core.logging_config import logger


class UsageService:
    """تسجيل وتتبع الاستخدام"""

    def __init__(self):
        self.repo = UsageRepository()

    def log_request(self, thread_id: str, tokens_input: int = 0,
                    tokens_output: int = 0, model: str = None):
        """تسجيل طلب"""
        try:
            self.repo.log(
                thread_id=thread_id,
                model=model or AI_MODEL_NAME,
                tokens_input=tokens_input,
                tokens_output=tokens_output,
                cost_usd=0.0  # محلي = مجاني
            )
        except Exception as e:
            logger.error(f"❌ خطأ في تسجيل الاستخدام: {e}")

    def get_stats(self) -> dict:
        """إحصائيات الاستخدام"""
        return self.repo.get_total_usage()


usage_service = UsageService()
