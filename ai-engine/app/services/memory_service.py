# app/services/memory_service.py
"""
خدمة الذاكرة - إدارة ذاكرة المحادثات
"""
import json
from app.repositories.memory_repo import MemoryRepository
from app.repositories.message_repo import MessageRepository
from app.config import settings
from app.core.logging_config import logger


class MemoryService:
    """إدارة ذاكرة المحادثات"""

    def __init__(self):
        self.memory_repo = MemoryRepository()
        self.message_repo = MessageRepository()

    def get_context(self, thread_id: str) -> str:
        """جلب سياق الذاكرة للمحادثة"""
        memory = self.memory_repo.get(thread_id)
        if not memory:
            return ""

        parts = []
        if memory.get("summary"):
            parts.append(f"ملخص المحادثة: {memory['summary']}")

        key_facts = memory.get("key_facts", [])
        if isinstance(key_facts, str):
            try:
                key_facts = json.loads(key_facts)
            except (json.JSONDecodeError, TypeError):
                key_facts = []

        if key_facts:
            facts_text = "، ".join(str(f) for f in key_facts[:10])
            parts.append(f"حقائق مهمة: {facts_text}")

        return " | ".join(parts)

    def get_recent_history(self, thread_id: str, limit: int = None) -> list:
        """جلب آخر رسائل المحادثة كسجل"""
        limit = limit or settings.MAX_MEMORY_MESSAGES
        messages = self.message_repo.get_recent_messages(thread_id, limit)
        # عكس الترتيب ليكون من الأقدم للأحدث
        messages.reverse()
        return [
            {
                "role": msg.get("role", "user"),
                "content": msg.get("content", ""),
            }
            for msg in messages
        ]

    def update_memory(self, thread_id: str):
        """تحديث ذاكرة المحادثة"""
        messages = self.message_repo.get_thread_messages(thread_id, limit=50)
        if not messages:
            return

        msg_count = len(messages)

        # استخراج ملخص
        summary = self._summarize_messages(messages)

        # استخراج حقائق مهمة
        key_facts = self._extract_key_facts(messages)

        # حفظ/تحديث الذاكرة
        self.memory_repo.upsert(thread_id, summary, key_facts)
        logger.info(f"💾 تم تحديث ذاكرة المحادثة {thread_id} ({msg_count} رسالة)")

    def _summarize_messages(self, messages: list) -> str:
        """تلخيص الرسائل"""
        if not messages:
            return ""

        # جمع أسئلة المستخدم
        user_questions = []
        for msg in messages:
            if msg.get("role") == "user":
                content = msg.get("content", "")
                if content:
                    # اختصار السؤال
                    short = content[:100] + "..." if len(content) > 100 else content
                    user_questions.append(short)

        if not user_questions:
            return "محادثة بدون أسئلة واضحة"

        total = len(messages)
        topics = user_questions[:5]  # أول 5 مواضيع
        summary = f"محادثة تحتوي على {total} رسالة. المواضيع: {' | '.join(topics)}"

        return summary[:500]

    def _extract_key_facts(self, messages: list) -> list:
        """استخراج حقائق مهمة من المحادثة"""
        facts = []

        for msg in messages:
            role = msg.get("role", "")
            content = msg.get("content", "")

            if role == "user" and content:
                # حفظ الأسئلة كحقائق
                short = content[:80]
                facts.append(f"سأل: {short}")

            elif role == "assistant" and content:
                # حفظ بداية الإجابات
                short = content[:80]
                facts.append(f"أجاب: {short}")

        # احتفظ بآخر 20 حقيقة
        return facts[-20:]

    def clear_memory(self, thread_id: str):
        """مسح ذاكرة المحادثة"""
        self.memory_repo.delete(thread_id)
        logger.info(f"🗑️ تم مسح ذاكرة المحادثة {thread_id}")


# إنشاء instance
memory_service = MemoryService()
