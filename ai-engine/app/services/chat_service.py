# app/services/chat_service.py
"""
خدمة الدردشة الرئيسية - المنسق الأساسي
"""
import time
from app.services.rag_service import rag_service
from app.services.memory_service import memory_service
from app.services.usage_service import usage_service
from app.services.vision_service import vision_service
from app.repositories.thread_repo import ThreadRepository
from app.repositories.message_repo import MessageRepository
from app.utils.text_processing import count_tokens, detect_language
from app.core.constants import ROLE_USER, ROLE_ASSISTANT, AI_MODEL_NAME, SYSTEM_MESSAGES
from app.core.logging_config import logger


class ChatService:
    """خدمة الدردشة - المنسق الرئيسي"""

    def __init__(self):
        self.thread_repo = ThreadRepository()
        self.message_repo = MessageRepository()

    def chat(self, question: str, thread_id: str = None,
             image_file_id: str = None, image_path: str = None) -> dict:
        """
        معالجة سؤال والرد عليه
        
        التدفق:
        1. إنشاء/استرجاع المحادثة
        2. تحليل الصورة (إن وجدت)
        3. جلب سياق الذاكرة
        4. بحث RAG في قاعدة المعرفة
        5. توليد الإجابة
        6. حفظ الرسائل
        7. تحديث الذاكرة
        8. تسجيل الاستخدام
        """
        start_time = time.time()

        # 1. إنشاء أو استرجاع المحادثة
        if not thread_id:
            thread_title = question[:80] if question else "محادثة جديدة"
            thread_id = self.thread_repo.create(title=thread_title)
            is_new_thread = True
        else:
            existing = self.thread_repo.get_by_id(thread_id)
            if not existing:
                thread_id = self.thread_repo.create(title=question[:80])
                is_new_thread = True
            else:
                is_new_thread = False

        # 2. تحليل الصورة إن وجدت
        image_context = ""
        vision_result = None
        if image_file_id and image_path:
            vision_result = vision_service.analyze_image(
                file_id=image_file_id,
                file_path=image_path,
            )
            if vision_result.get("extracted_text"):
                image_context = f"نص مستخرج من الصورة: {vision_result['extracted_text']}"
            if vision_result.get("description"):
                image_context += f"\nوصف الصورة: {vision_result['description']}"

        # 3. جلب سياق الذاكرة
        memory_context = memory_service.get_context(thread_id)

        # 4. جمع السؤال مع سياق الصورة
        full_query = question
        if image_context:
            full_query = f"{question}\n\n{image_context}"

        # 5. بحث RAG
        relevant_chunks = rag_service.search(full_query)
        context = rag_service.build_context(relevant_chunks)

        # 6. توليد الإجابة
        answer = rag_service.generate_answer(full_query, context, memory_context)

        # حساب الزمن
        latency_ms = int((time.time() - start_time) * 1000)

        # 7. حفظ رسالة المستخدم
        input_tokens = count_tokens(question)
        user_msg_id = self.message_repo.create(
            thread_id=thread_id,
            role=ROLE_USER,
            content=question,
            language=detect_language(question),
            tokens=input_tokens,
        )

        # 8. حفظ رسالة المساعد
        output_tokens = count_tokens(answer)
        citations = [
            {
                "chunk_id": c.get("id"),
                "score": c.get("_score", 0),
                "preview": c.get("content", "")[:100],
            }
            for c in relevant_chunks[:3]
        ]

        assistant_msg_id = self.message_repo.create(
            thread_id=thread_id,
            role=ROLE_ASSISTANT,
            content=answer,
            model=AI_MODEL_NAME,
            tokens=output_tokens,
            latency_ms=latency_ms,
            citations=citations,
            language=detect_language(answer),
        )

        # ربط الصورة بالرسالة
        if image_file_id:
            from app.repositories.file_repo import FileRepository
            FileRepository.link_to_message(user_msg_id, image_file_id)

        # 9. تحديث الذاكرة
        try:
            memory_service.update_memory(thread_id)
        except Exception as e:
            logger.error(f"⚠️ خطأ في تحديث الذاكرة: {e}")

        # 10. تسجيل الاستخدام
        usage_service.log_request(
            thread_id=thread_id,
            tokens_input=input_tokens,
            tokens_output=output_tokens,
        )

        logger.info(f"✅ تم الرد على السؤال في {latency_ms}ms ({len(relevant_chunks)} مصادر)")

        return {
            "thread_id": thread_id,
            "message_id": assistant_msg_id,
            "answer": answer,
            "sources": [
                {
                    "chunk_id": c.get("id"),
                    "content_preview": c.get("content", "")[:150],
                    "score": c.get("_score", 0),
                }
                for c in relevant_chunks[:5]
            ],
            "metadata": {
                "model": AI_MODEL_NAME,
                "input_tokens": input_tokens,
                "output_tokens": output_tokens,
                "latency_ms": latency_ms,
                "sources_found": len(relevant_chunks),
                "is_new_thread": is_new_thread,
                "has_image": bool(image_file_id),
                "has_memory": bool(memory_context),
            },
            "vision": vision_result if vision_result else None,
        }

    def get_thread_history(self, thread_id: str) -> dict:
        """جلب تاريخ المحادثة"""
        thread = self.thread_repo.get_by_id(thread_id)
        if not thread:
            return None

        messages = self.message_repo.get_thread_messages(thread_id)
        memory = memory_service.get_context(thread_id)

        return {
            "thread": thread,
            "messages": messages,
            "memory_context": memory,
            "message_count": len(messages),
        }


# إنشاء instance
chat_service = ChatService()
