# app/services/embedding_service.py
"""
خدمة التضمين والبحث - TF-IDF محلي بدون OpenAI
"""
import re
import math
from collections import Counter
from app.utils.text_processing import normalize_arabic, tokenize, remove_stop_words
from app.core.logging_config import logger


class EmbeddingService:
    """خدمة TF-IDF للبحث الدلالي المحلي"""

    def __init__(self):
        self._idf_cache = {}
        self._doc_count = 0

    def compute_tfidf(self, query_tokens: list, doc_tokens: list) -> float:
        """حساب تشابه TF-IDF بين استعلام ومستند"""
        if not query_tokens or not doc_tokens:
            return 0.0

        # TF للمستند
        doc_counter = Counter(doc_tokens)
        doc_len = len(doc_tokens)

        score = 0.0
        for token in query_tokens:
            tf = doc_counter.get(token, 0) / max(doc_len, 1)
            score += tf

        return score / max(len(query_tokens), 1)

    def rank_chunks(self, query: str, chunks: list) -> list:
        """
        ترتيب القطع حسب الأكثر صلة بالاستعلام
        
        يستخدم مزيجاً من:
        1. TF-IDF تقريبي
        2. تطابق كلمات مفتاحية
        3. تطابق عبارات
        """
        if not chunks or not query:
            return []

        # تجهيز الاستعلام
        query_normalized = normalize_arabic(query.lower())
        query_tokens = remove_stop_words(tokenize(query_normalized))
        query_lower = query.lower().strip()

        scored_chunks = []

        for chunk in chunks:
            content = chunk.get("content", "")
            if not content:
                continue

            content_normalized = normalize_arabic(content.lower())
            content_tokens = tokenize(content_normalized)
            content_lower = content.lower()

            # 1. نقاط TF-IDF
            tfidf_score = self.compute_tfidf(query_tokens, content_tokens)

            # 2. نقاط تطابق الكلمات المفتاحية
            keyword_matches = sum(1 for t in query_tokens if t in content_normalized)
            keyword_score = keyword_matches / max(len(query_tokens), 1)

            # 3. نقاط تطابق العبارة الكاملة
            phrase_score = 1.0 if query_lower in content_lower else 0.0

            # 4. نقاط تطابق الأسئلة (إذا كان المحتوى يحتوي على سؤال/جواب)
            qa_score = 0.0
            if "سؤال" in content_lower or "جواب" in content_lower:
                # استخرج السؤال من المحتوى
                question_match = re.search(r'سؤال\s*[:：]\s*(.*?)(?:جواب|$)', content, re.DOTALL)
                if question_match:
                    stored_question = normalize_arabic(question_match.group(1).lower().strip())
                    # تطابق مع السؤال المخزن
                    q_tokens = remove_stop_words(tokenize(stored_question))
                    common = set(query_tokens) & set(q_tokens)
                    qa_score = len(common) / max(len(query_tokens), 1) * 1.5

            # النقاط النهائية (مرجّحة)
            final_score = (
                tfidf_score * 0.25 +
                keyword_score * 0.30 +
                phrase_score * 0.20 +
                qa_score * 0.25
            )

            scored_chunks.append({
                **chunk,
                "_score": round(final_score, 4),
                "_keyword_matches": keyword_matches,
            })

        # ترتيب تنازلياً
        scored_chunks.sort(key=lambda x: x["_score"], reverse=True)

        return scored_chunks


# إنشاء instance واحد
embedding_service = EmbeddingService()
