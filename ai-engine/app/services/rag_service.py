# app/services/rag_service.py
"""
خدمة RAG - البحث والاسترجاع من قاعدة المعرفة
"""
import re
from app.repositories.chunk_repo import ChunkRepository
from app.services.embedding_service import embedding_service
from app.utils.text_processing import extract_keywords, normalize_arabic
from app.config import settings
from app.core.logging_config import logger


class RAGService:
    """البحث والاسترجاع من قاعدة المعرفة"""

    def __init__(self):
        self.chunk_repo = ChunkRepository()

    def search(self, query: str, top_k: int = None) -> list:
        """
        بحث ذكي في قاعدة المعرفة
        
        الخطوات:
        1. استخراج كلمات مفتاحية
        2. بحث في القطع بالكلمات
        3. ترتيب بالصلة (TF-IDF)
        4. إرجاع أفضل النتائج
        """
        top_k = top_k or settings.TOP_K_RESULTS
        
        logger.info(f"🔍 بحث RAG: {query[:100]}...")

        # 1. استخراج كلمات مفتاحية
        keywords = extract_keywords(query)
        logger.info(f"📝 كلمات مفتاحية: {keywords}")

        if not keywords:
            # إذا لم يتم استخراج كلمات، استخدم كلمات الاستعلام مباشرة
            keywords = query.split()

        # 2. بحث في القطع
        # بحث بالكلمات المفتاحية
        raw_chunks = self.chunk_repo.fulltext_search(keywords, limit=50)

        # إذا لم نجد نتائج، جرب بحث أوسع
        if not raw_chunks:
            # جرب بكلمات أقل
            for kw in keywords[:3]:
                results = self.chunk_repo.search_by_content(kw, limit=20)
                raw_chunks.extend(results)

        # إذا لا نتائج بعد، جلب كل القطع
        if not raw_chunks:
            raw_chunks = self.chunk_repo.get_all(limit=100)

        # إزالة التكرار
        seen = set()
        unique_chunks = []
        for chunk in raw_chunks:
            chunk_id = chunk.get("id")
            if chunk_id not in seen:
                seen.add(chunk_id)
                unique_chunks.append(chunk)

        # 3. ترتيب بالصلة
        ranked = embedding_service.rank_chunks(query, unique_chunks)

        # 4. تصفية بالحد الأدنى من الصلة
        filtered = [
            c for c in ranked
            if c.get("_score", 0) >= settings.MIN_RELEVANCE_SCORE
        ]

        # إذا لم يتجاوز أي شيء الحد، أرجع أفضل ما لدينا
        if not filtered and ranked:
            filtered = ranked[:top_k]

        results = filtered[:top_k]
        logger.info(f"✅ تم العثور على {len(results)} نتيجة ذات صلة")

        return results

    def build_context(self, relevant_chunks: list) -> str:
        """بناء سياق من القطع المسترجعة"""
        if not relevant_chunks:
            return ""

        context_parts = []
        for i, chunk in enumerate(relevant_chunks, 1):
            content = chunk.get("content", "")
            score = chunk.get("_score", 0)
            context_parts.append(f"[مصدر {i} - صلة: {score:.0%}]\n{content}")

        return "\n\n---\n\n".join(context_parts)

    def generate_answer(self, query: str, context: str, memory_context: str = "") -> str:
        """
        توليد إجابة ذكية من السياق المسترجع
        
        هذا النظام لا يستخدم OpenAI - يعتمد على:
        1. البحث عن إجابة مباشرة في القطع
        2. تجميع المعلومات ذات الصلة
        3. تنسيق الإجابة بشكل منطقي
        """
        if not context:
            return "لم أجد معلومات كافية في قاعدة المعرفة للإجابة على سؤالك. يمكنك إعادة صياغة السؤال أو إضافة معلومات إلى قاعدة المعرفة."

        # 1. محاولة العثور على إجابة مباشرة (نمط سؤال/جواب)
        direct_answer = self._find_direct_answer(query, context)
        if direct_answer:
            return direct_answer

        # 2. تجميع معلومات ذات صلة
        return self._compile_answer(query, context, memory_context)

    def _find_direct_answer(self, query: str, context: str) -> str:
        """البحث عن إجابة مباشرة في نمط سؤال/جواب"""
        query_normalized = normalize_arabic(query.lower())
        
        # البحث عن أنماط سؤال/جواب
        qa_pattern = re.compile(
            r'سؤال\s*[:：]\s*(.*?)\s*جواب\s*[:：]\s*(.*?)(?=سؤال\s*[:：]|---|\[مصدر|$)',
            re.DOTALL
        )
        
        matches = qa_pattern.findall(context)
        
        best_match = None
        best_score = 0
        
        for question, answer in matches:
            q_normalized = normalize_arabic(question.lower().strip())
            # حساب تشابه بسيط
            q_words = set(q_normalized.split())
            query_words = set(query_normalized.split())
            common = q_words & query_words
            
            if not q_words:
                continue
                
            score = len(common) / max(len(query_words), 1)
            
            if score > best_score and score > 0.3:
                best_score = score
                best_match = answer.strip()
        
        return best_match

    def _compile_answer(self, query: str, context: str, memory_context: str = "") -> str:
        """تجميع إجابة من المعلومات المتاحة"""
        # حذف البيانات الوصفية والعناوين من السياق
        clean_context = re.sub(r'\[مصدر \d+ - صلة: \d+%\]', '', context)
        clean_context = clean_context.replace('---', '').strip()
        
        # تجميع الأجزاء ذات الصلة
        parts = [p.strip() for p in clean_context.split('\n\n') if p.strip()]
        
        if len(parts) == 1:
            answer = f"بناءً على المعلومات المتاحة في قاعدة المعرفة:\n\n{parts[0]}"
        else:
            answer = "بناءً على المعلومات المتاحة في قاعدة المعرفة:\n\n"
            for i, part in enumerate(parts[:5], 1):
                # تنظيف الجزء
                part_clean = part.strip()
                if part_clean:
                    answer += f"{part_clean}\n\n"
        
        if memory_context:
            answer += f"\n📝 ملاحظة: {memory_context}"
        
        return answer.strip()


# إنشاء instance واحد
rag_service = RAGService()
