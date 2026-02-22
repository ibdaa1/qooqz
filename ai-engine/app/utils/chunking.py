# app/utils/chunking.py
"""
تقطيع النصوص إلى قطع (Chunking)
"""
from app.config import settings
from app.utils.text_processing import count_tokens


def chunk_text(text: str, chunk_size: int = None, overlap: int = None) -> list:
    """
    تقطيع النص إلى قطع مع تداخل
    
    Args:
        text: النص المراد تقطيعه
        chunk_size: حجم كل قطعة (بالكلمات)
        overlap: حجم التداخل بين القطع
    
    Returns:
        قائمة من القطع النصية
    """
    if not text or not text.strip():
        return []

    chunk_size = chunk_size or settings.CHUNK_SIZE
    overlap = overlap or settings.CHUNK_OVERLAP

    # تقسيم إلى فقرات أولاً
    paragraphs = text.split('\n')
    paragraphs = [p.strip() for p in paragraphs if p.strip()]

    chunks = []
    current_chunk = []
    current_size = 0

    for paragraph in paragraphs:
        p_words = len(paragraph.split())

        # إذا الفقرة أكبر من حجم القطعة، قسمها
        if p_words > chunk_size:
            # احفظ القطعة الحالية إذا كانت بها محتوى
            if current_chunk:
                chunks.append('\n'.join(current_chunk))
                # احتفظ بالتداخل
                overlap_text = _get_overlap(current_chunk, overlap)
                current_chunk = [overlap_text] if overlap_text else []
                current_size = len(overlap_text.split()) if overlap_text else 0

            # قسّم الفقرة الكبيرة
            words = paragraph.split()
            for i in range(0, len(words), chunk_size - overlap):
                chunk_words = words[i:i + chunk_size]
                chunks.append(' '.join(chunk_words))

        elif current_size + p_words > chunk_size:
            # القطعة الحالية ممتلئة
            if current_chunk:
                chunks.append('\n'.join(current_chunk))
                overlap_text = _get_overlap(current_chunk, overlap)
                current_chunk = [overlap_text] if overlap_text else []
                current_size = len(overlap_text.split()) if overlap_text else 0

            current_chunk.append(paragraph)
            current_size += p_words
        else:
            current_chunk.append(paragraph)
            current_size += p_words

    # آخر قطعة
    if current_chunk:
        chunks.append('\n'.join(current_chunk))

    return chunks


def _get_overlap(chunk_parts: list, overlap_words: int) -> str:
    """استخراج نص التداخل من نهاية القطعة"""
    full_text = '\n'.join(chunk_parts)
    words = full_text.split()
    if len(words) <= overlap_words:
        return full_text
    return ' '.join(words[-overlap_words:])


def chunk_qa_text(text: str) -> list:
    """
    تقطيع نص يحتوي على أسئلة وأجوبة (سؤال: ... جواب: ...)
    يحافظ على كل سؤال+جواب معاً
    """
    if not text:
        return []

    # نمط البحث عن أسئلة وأجوبة
    import re
    qa_pattern = re.compile(r'(سؤال\s*[:：]\s*.*?(?:جواب\s*[:：]\s*.*?)(?=سؤال\s*[:：]|$))', re.DOTALL)
    matches = qa_pattern.findall(text)

    if matches:
        return [m.strip() for m in matches if m.strip()]

    # إذا لم يتم العثور على نمط Q&A، استخدم التقطيع العادي
    return chunk_text(text)
