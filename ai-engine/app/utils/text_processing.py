# app/utils/text_processing.py
"""
معالجة النصوص - تطبيع، ترميز، إزالة كلمات التوقف
"""
import re
from app.core.constants import ARABIC_STOP_WORDS


def normalize_arabic(text: str) -> str:
    """تطبيع النص العربي"""
    if not text:
        return ""
    # إزالة التشكيل
    text = re.sub(r'[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E8\u06EA-\u06ED]', '', text)
    # توحيد الهمزات
    text = re.sub(r'[إأآا]', 'ا', text)
    # توحيد التاء المربوطة
    text = text.replace('ة', 'ه')
    # توحيد الياء
    text = text.replace('ى', 'ي')
    # إزالة الأرقام العربية وتحويلها
    arabic_nums = '٠١٢٣٤٥٦٧٨٩'
    for i, num in enumerate(arabic_nums):
        text = text.replace(num, str(i))
    return text.strip()


def tokenize(text: str) -> list:
    """تقسيم النص إلى كلمات"""
    if not text:
        return []
    # إزالة علامات الترقيم
    text = re.sub(r'[^\w\s]', ' ', text)
    # تقسيم إلى كلمات
    words = text.split()
    return [w.strip() for w in words if w.strip()]


def remove_stop_words(words: list) -> list:
    """إزالة كلمات التوقف"""
    return [w for w in words if w not in ARABIC_STOP_WORDS and len(w) > 1]


def extract_keywords(text: str) -> list:
    """استخراج الكلمات المفتاحية من النص"""
    normalized = normalize_arabic(text)
    words = tokenize(normalized)
    keywords = remove_stop_words(words)
    return list(set(keywords))


def count_tokens(text: str) -> int:
    """عدد تقريبي للتوكنات"""
    if not text:
        return 0
    return len(text.split())


def detect_language(text: str) -> str:
    """كشف اللغة (بسيط)"""
    if not text:
        return "ar"
    arabic_pattern = re.compile(r'[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]+')
    arabic_matches = arabic_pattern.findall(text)
    total_words = len(text.split())
    if total_words == 0:
        return "ar"
    arabic_ratio = len(arabic_matches) / total_words
    return "ar" if arabic_ratio > 0.3 else "en"


def clean_text(text: str) -> str:
    """تنظيف النص"""
    if not text:
        return ""
    # إزالة مسافات مكررة
    text = re.sub(r'\s+', ' ', text)
    # إزالة أسطر فارغة مكررة
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip()
