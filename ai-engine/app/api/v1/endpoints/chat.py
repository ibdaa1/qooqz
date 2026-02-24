# app/api/v1/endpoints/chat.py
"""
🤖 نقطة نهاية الدردشة - نسخة الإنتاج
بحث ذكي + معالجة حقيقية للملفات والصور (OCR/PDF/Docs)
"""
import re
import os
import uuid
import time
import json
from collections import Counter
from fastapi import APIRouter, HTTPException, Form, UploadFile, File
from typing import Optional
from app.db.mysql_conn import execute_query
from app.utils.file_processor import extract_text_from_file, summarize_extracted_text

router = APIRouter()

UPLOAD_DIR = os.environ.get("UPLOAD_DIR", "uploads")

# ===== كلمات التوقف العربية (موسّعة) =====
STOP_WORDS = {
    "في", "من", "على", "إلى", "الى", "عن", "مع", "هذا", "هذه", "ذلك", "تلك",
    "التي", "الذي", "اللذان", "اللتان", "الذين", "اللاتي", "اللواتي",
    "هو", "هي", "هم", "هن", "أنا", "نحن", "أنت", "أنتم", "أنتن",
    "كان", "كانت", "يكون", "تكون", "كانوا", "ليس", "ليست",
    "ما", "لا", "لم", "لن", "قد", "سوف", "سأ", "سيكون",
    "و", "أو", "ثم", "ف", "لكن", "بل", "إن", "أن", "ان",
    "كل", "بعض", "أي", "كيف", "أين", "متى", "لماذا", "ماذا",
    "هل", "إذا", "عند", "عندما", "حتى", "منذ", "بين",
    "هنا", "هناك", "الآن", "أيضاً", "أيضا", "جداً", "جدا", "فقط",
    "ال", "لل", "بال", "غير", "بدون", "حول", "خلال",
    "يا", "لي", "لك", "له", "لها", "لنا", "لهم", "لهن",
    "عن", "الي", "علي", "فيه", "فيها", "منه", "منها",
    "أنه", "أنها", "إنه", "إنها", "لأن", "لان",
    "كما", "مثل", "مثلا", "حيث", "بعد", "قبل", "فوق", "تحت",
    "هذي", "هاذا", "هاذي", "ذا", "دا", "دي", "اللي",
    "شو", "ايش", "وش", "كيفا", "ليش", "ليه",
    "يعني", "طيب", "خلاص", "بس", "كمان", "برضه", "برضو",
    "ممكن", "يمكن", "لازم", "عشان", "علشان",
    "is", "the", "a", "an", "and", "or", "what", "how", "why",
    "can", "do", "does", "are", "am", "was", "were", "be", "to", "of",
    "in", "on", "at", "for", "with", "about", "it", "this", "that",
}

# ===== مرادفات الأسئلة =====
QUESTION_SYNONYMS = {
    "explain": ["اشرح", "وضح", "فسر", "بين", "حدثني", "explain"],
    "what": ["ما", "ماهو", "ماهي", "ايش", "شو", "وش", "ماذا", "عرف", "عرفني", "what"],
    "how": ["كيف", "كيفية", "طريقة", "ازاي", "شلون", "how"],
    "why": ["لماذا", "ليش", "ليه", "لمَ", "why"],
    "difference": ["فرق", "اختلاف", "مقارنة", "فارق", "difference", "compare", "vs"],
    "tell": ["اخبرني", "خبرني", "قلي", "قولي", "احكيلي", "tell"],
    "know": ["اعرف", "عايز", "ابغى", "ابي", "اريد", "know"],
}


def normalize_arabic(text):
    """تطبيع شامل للنص العربي"""
    if not text:
        return ""
    # إزالة التشكيل
    text = re.sub(r'[\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E8\u06EA-\u06ED]', '', text)
    # توحيد الهمزات
    text = re.sub(r'[إأآٱ]', 'ا', text)
    text = re.sub(r'[ؤ]', 'و', text)
    text = re.sub(r'[ئ]', 'ي', text)
    # توحيد حروف
    text = text.replace('ة', 'ه')
    text = text.replace('ى', 'ي')
    text = text.replace('ك', 'ك')
    # إزالة التطويل
    text = re.sub(r'ـ+', '', text)
    # إزالة تكرارات الأحرف
    text = re.sub(r'(.)\1{2,}', r'\1', text)
    # حذف "ال" التعريف من بداية الكلمات
    words = text.split()
    cleaned = []
    for w in words:
        w = w.strip()
        if w.startswith('ال') and len(w) > 3:
            cleaned.append(w[2:])
            cleaned.append(w)
        else:
            cleaned.append(w)
    return ' '.join(cleaned).strip()


def extract_keywords(text):
    """استخراج كلمات مفتاحية ذكية"""
    normalized = normalize_arabic(text.lower())
    clean = re.sub(r'[^\w\s]', ' ', normalized)
    words = [w.strip() for w in clean.split() if w.strip()]
    keywords = [w for w in words if w not in STOP_WORDS and len(w) > 1] # تم تقليل الحد لـ 1

    stems = set()
    for kw in keywords:
        stems.add(kw)
        for prefix in ['ال', 'وال', 'بال', 'لل', 'فال', 'كال', 'ولل']:
            if kw.startswith(prefix) and len(kw) > len(prefix) + 2:
                stems.add(kw[len(prefix):])
        for suffix in ['ات', 'ين', 'ون', 'ان', 'ها', 'هم', 'ية', 'يه', 'كم', 'نا']:
            if kw.endswith(suffix) and len(kw) > len(suffix) + 2:
                stems.add(kw[:-len(suffix)])

    return list(stems) if stems else words[:5]


def fuzzy_match(word1, word2):
    """مطابقة ضبابية محسّنة"""
    if not word1 or not word2:
        return 0.0
    w1 = normalize_arabic(word1.lower())
    w2 = normalize_arabic(word2.lower())

    if w1 == w2:
        return 1.0

    if w1 in w2 or w2 in w1:
        shorter = min(len(w1), len(w2))
        longer = max(len(w1), len(w2))
        return shorter / longer

    set1 = set(w1)
    set2 = set(w2)
    intersection = set1 & set2
    union = set1 | set2
    if not union:
        return 0.0
    jaccard = len(intersection) / len(union)

    common_prefix = 0
    for c1, c2 in zip(w1, w2):
        if c1 == c2:
            common_prefix += 1
        else:
            break
    prefix_bonus = common_prefix / max(len(w1), len(w2)) * 0.3

    return min(jaccard + prefix_bonus, 1.0)


def score_chunk(query, content):
    """حساب صلة القطعة بالسؤال"""
    if not content or not query:
        return 0.0

    query_norm = normalize_arabic(query.lower())
    content_norm = normalize_arabic(content.lower())

    q_clean = re.sub(r'[^\w\s]', ' ', query_norm)
    c_clean = re.sub(r'[^\w\s]', ' ', content_norm)
    q_words = [w for w in q_clean.split() if w not in STOP_WORDS and len(w) > 1]
    c_words = [w for w in c_clean.split() if len(w) > 1]
    c_set = set(c_words)

    if not q_words:
        return 0.0

    exact_matches = 0
    fuzzy_matches = 0
    for qw in q_words:
        if qw in c_set:
            exact_matches += 1
        else:
            best_fuzzy = max((fuzzy_match(qw, cw) for cw in c_words), default=0)
            if best_fuzzy > 0.6:
                fuzzy_matches += best_fuzzy

    keyword_score = (exact_matches + fuzzy_matches * 0.7) / len(q_words)

    phrase_score = 0.0
    if query_norm in content_norm:
        phrase_score = 1.0
    else:
        for i in range(len(q_words) - 2):
            trigram = ' '.join(q_words[i:i+3])
            if trigram in c_clean:
                phrase_score = 0.6
                break

    qa_score = 0.0
    qa_patterns = [
        r'سؤال\s*[:：؟?]\s*(.*?)(?:جواب|اجابه|الاجابه|الجواب)\s*[:：]\s*(.*?)(?=سؤال|$)',
        r'س\s*[:：]\s*(.*?)(?:ج|جواب)\s*[:：]\s*(.*?)(?=س\s*[:：]|$)',
    ]
    for pattern in qa_patterns:
        for q_text, a_text in re.findall(pattern, content, re.DOTALL):
            q_norm_inner = normalize_arabic(q_text.lower().strip())
            q_inner_words = [w for w in re.sub(r'[^\w\s]', ' ', q_norm_inner).split()
                            if w not in STOP_WORDS and len(w) > 1]
            if not q_inner_words:
                continue

            match_count = 0
            for qw in q_words:
                for qiw in q_inner_words:
                    if fuzzy_match(qw, qiw) > 0.55:
                        match_count += 1
                        break
            
            ratio = match_count / max(len(q_words), 1)
            if ratio > qa_score:
                qa_score = ratio * 1.5

    tf_counter = Counter(c_words)
    total_words = max(len(c_words), 1)
    tf_score = sum(tf_counter.get(kw, 0) for kw in q_words) / total_words

    topic_bonus = 0.0
    for group, synonyms in QUESTION_SYNONYMS.items():
        q_has = any(s in query_norm for s in synonyms)
        c_has = any(s in content_norm for s in synonyms)
        if q_has and c_has:
            topic_bonus = 0.1
            break

    final = (
        keyword_score * 0.25 +
        phrase_score * 0.15 +
        qa_score * 0.30 +
        tf_score * 0.15 +
        topic_bonus * 0.15
    )

    return round(min(final, 1.0), 4)


def find_direct_answer(query, chunks, context_text=""):
    """
    البحث عن إجابة مباشرة في القطع أو السياق الإضافي (الملفات)
    """
    all_content = [chunk.get("content", "") for chunk in chunks]
    if context_text:
        all_content.append(context_text)

    query_norm = normalize_arabic(query.lower())
    q_clean = re.sub(r'[^\w\s]', ' ', query_norm)
    q_words = [w for w in q_clean.split() if w not in STOP_WORDS and len(w) > 1]

    if not q_words:
        return None

    best_answer = None
    best_score = 0

    qa_patterns = [
        r'سؤال\s*[:：؟?]\s*(.*?)\s*(?:جواب|اجابه|الاجابه|الجواب)\s*[:：]\s*(.*?)(?=سؤال|$)',
        r'س\s*[:：]\s*(.*?)(?:ج|جواب)\s*[:：]\s*(.*?)(?=س\s*[:：]|$)',
    ]

    for content in all_content:
        for pattern in qa_patterns:
            for q_text, a_text in re.findall(pattern, content, re.DOTALL):
                q_stored = normalize_arabic(q_text.lower().strip())
                q_stored_words = [w for w in re.sub(r'[^\w\s]', ' ', q_stored).split()
                                 if w not in STOP_WORDS and len(w) > 1]

                if not q_stored_words:
                    continue

                match_count = 0
                for qw in q_words:
                    for sw in q_stored_words:
                        if fuzzy_match(qw, sw) > 0.5:
                            match_count += 1
                            break

                score = match_count / max(len(q_words), len(q_stored_words))

                if score > best_score and score > 0.25:
                    best_score = score
                    best_answer = a_text.strip()

    return best_answer


def build_smart_answer(question, top_chunks, file_context=None, memory_context=""):
    """بناء إجابة ذكية من القطع وسياق الملفات والذاكرة"""
    
    # 1. إجابة مباشرة (تشمل الذاكرة والملف)
    all_extra = " ".join(filter(None, [memory_context, str(file_context or "")]))
    direct = find_direct_answer(question, top_chunks, all_extra)
    if direct:
        return direct

    # 2. تجميع من الملفات المرفقة (الأولوية لها)
    parts = []
    
    if file_context and file_context.get("text"):
        file_text = file_context["text"]
        
        # إذا الملف صغير، استخدمه كله
        if len(file_text) < 500:
             parts.append(f"من الملف المرفق:\n{file_text}")
        else:
             # البحث في الملف عن الإجابة
             file_chunks = [file_text[i:i+500] for i in range(0, len(file_text), 400)]
             best_file_chunk = max(file_chunks, key=lambda c: score_chunk(question, c), default="")
             if score_chunk(question, best_file_chunk) > 0.1:
                 parts.append(f"من الملف المرفق:\n...{best_file_chunk}...")
             else:
                 parts.append(f"ملخص الملف المرفق:\n{file_text[:300]}...")

    # 3. تجميع من قاعدة المعرفة
    relevant = [c for c in top_chunks if c.get("_score", 0) > 0.05]
    for c in relevant[:3]:
        content = c.get("content", "").strip()
        qa_match = re.search(r'جواب\s*[:：]\s*(.*?)(?=سؤال|$)', content, re.DOTALL)
        if qa_match:
            parts.append(qa_match.group(1).strip())
        else:
            parts.append(content)

    if not parts:
        # 4. الذاكرة — إذا كان السؤال يتعلق بمحادثة سابقة
        if memory_context:
            mem_score = score_chunk(question, memory_context)
            if mem_score > 0.1:
                return f"بناءً على محادثتنا السابقة:\n\n{memory_context[:600]}"
        if file_context and file_context.get("text"):
            ft = file_context["text"]
            if "📷" in ft or "صورة" in ft or "image" in ft.lower():
                return (
                    f"📎 استلمت الملف المرفق: {file_context.get('filename','')}\n\n"
                    f"{ft}\n\n"
                    "💡 لقراءة النصوص داخل الصور بدقة، يحتاج النظام إلى تثبيت أداة OCR (pytesseract)."
                )
            return f"📎 استلمت الملف المرفق.\n\nمعلومات الملف:\n{ft}"
        return "عذراً، لم أجد معلومات كافية في قاعدة المعرفة للإجابة على سؤالك. يمكنك إعادة صياغة السؤال."

    if len(parts) == 1:
        return parts[0]
    else:
        combined = "\n\n".join(parts)
        return f"بناءً على المعلومات المتاحة:\n\n{combined}"


@router.post("/chat")
def chat(question: str = Form(...), thread_id: Optional[str] = Form(None)):
    """دردشة نصية فقط"""
    # ... (نفس المنطق السابق، لكن تم نقله لدالة مشتركة للاختصار) ...
    return process_chat_request(question, thread_id, None)


@router.post("/chat/json")
def chat_json(request: dict):
    question = request.get("question", "").strip()
    thread_id = request.get("thread_id")
    if not question:
        raise HTTPException(status_code=400, detail="السؤال مطلوب")
    return process_chat_request(question, thread_id, None)


@router.post("/chat/with-image")
async def chat_with_image(
    question: str = Form(...),
    thread_id: Optional[str] = Form(None),
    image: UploadFile = File(None),  # قد يكون ملف أو صورة
):
    """دردشة مع ملف (صورة، PDF، مستند)"""
    file_info = None
    if image:
        try:
            content = await image.read()
            
            # معالجة الملف واستخراج النص (تمرير اسم الملف لاستخراج الامتداد بشكل صحيح)
            file_result = extract_text_from_file(image.filename, image.content_type, content)
            
            # حفظ الملف
            os.makedirs(UPLOAD_DIR, exist_ok=True)
            safe_name = f"{uuid.uuid4()}_{image.filename}"
            file_path = os.path.join(UPLOAD_DIR, safe_name)
            with open(file_path, "wb") as f:
                f.write(content)
                
            # حفظ في DB
            file_id = str(uuid.uuid4())
            try:
                execute_query(
                    "INSERT INTO ai_files (id, filename, mime_type, file_size, file_path, extracted_text) VALUES (%s, %s, %s, %s, %s, %s)",
                    (file_id, image.filename, image.content_type, len(content), file_path, file_result.get("text", "")[:5000])
                )
            except:
                pass
            
            file_info = {
                "file_id": file_id,
                "filename": image.filename,
                "text": file_result.get("text", ""),
                "type": file_result.get("metadata", {}).get("type", "unknown")
            }
            
        except Exception as e:
            print(f"File process error: {e}")

    return process_chat_request(question, thread_id, file_info)


def process_chat_request(question: str, thread_id: Optional[str], file_context: Optional[dict]):
    """منطق الدردشة المشترك"""
    start_time = time.time()
    question = question.strip() if question else ""

    # استخراج محتوى الملف المضمّن في السؤال (من PHP two-step upload)
    if file_context is None and "[محتوى الملف المرفق" in question:
        try:
            marker = "[محتوى الملف المرفق"
            m_start = question.index(marker)
            clean_question = question[:m_start].strip()
            block = question[m_start:]
            # استخراج اسم الملف
            fname = "ملف"
            if "'" in block:
                try:
                    fname = block[block.index("'") + 1 : block.index("':\n")]
                except Exception:
                    pass
            # استخراج المحتوى
            content = ""
            if "':\n" in block:
                content = block[block.index("':\n") + 3:].rstrip("]").strip()
            file_context = {"filename": fname, "text": content, "type": "uploaded"}
            question = clean_question or question
        except Exception:
            pass

    if not question and not file_context:
        raise HTTPException(status_code=400, detail="السؤال أو الملف مطلوب")

    # 1. Thread Management
    is_new_thread = False
    if not thread_id:
        thread_id = str(uuid.uuid4())
        try:
            execute_query("INSERT INTO ai_threads (id, title, metadata) VALUES (%s, %s, %s)", (thread_id, question[:80], '{}'))
            is_new_thread = True
        except: pass

    # 2. Load thread memory (for continuing conversations)
    memory_context = ""
    if thread_id and not is_new_thread:
        try:
            mem_rows = execute_query(
                "SELECT key_facts FROM ai_thread_memory WHERE thread_id = %s",
                (thread_id,)
            ) or []
            if mem_rows:
                key_facts = json.loads(mem_rows[0].get("key_facts") or "[]")
                if key_facts:
                    memory_context = "سياق المحادثة:\n" + "\n".join([
                        f"س: {f['q']}\nج: {f['a'][:150]}" for f in key_facts[-5:]
                    ])
        except Exception as me:
            print(f"Memory load error: {me}")
    
    # 2. Search
    keywords = extract_keywords(question)
    raw_chunks = []
    
    # Search logic (same as before)
    if keywords:
        conditions = " OR ".join(["content LIKE %s" for _ in keywords])
        params = tuple(f"%{kw}%" for kw in keywords)
        results = execute_query(f"SELECT id, content FROM ai_document_chunks WHERE {conditions} LIMIT 50", params) or []
        raw_chunks.extend(results)
        
    for chunk in raw_chunks:
        chunk["_score"] = score_chunk(question, chunk.get("content", ""))
    
    raw_chunks.sort(key=lambda x: x.get("_score", 0), reverse=True)
    top_chunks = raw_chunks[:10]

    # 3. Build Answer (with file context and memory)
    answer = build_smart_answer(question, top_chunks, file_context, memory_context)

    # 4. Save & Return
    latency_ms = int((time.time() - start_time) * 1000)
    asst_msg_id = str(uuid.uuid4())  # define early to avoid NameError if save fails

    # Save messages...
    try:
        user_msg_id = str(uuid.uuid4())
        
        # User message
        content_to_save = question
        if file_context:
            content_to_save += f"\n[مرفق: {file_context['filename']}]"
            
        execute_query(
            "INSERT INTO ai_messages (id, thread_id, role, content, language, tokens) VALUES (%s, %s, %s, %s, %s, %s)",
            (user_msg_id, thread_id, 'user', content_to_save, 'ar', len(question.split()))
        )
        
        # Assistant message
        execute_query(
            "INSERT INTO ai_messages (id, thread_id, role, content, model, tokens, latency_ms, language) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
            (asst_msg_id, thread_id, 'assistant', answer, 'local-rag-v1', len(answer.split()), latency_ms, 'ar')
        )
        
        # Link file if exists (only when file_id is available)
        if file_context and file_context.get('file_id'):
             execute_query("INSERT INTO ai_message_files (message_id, file_id) VALUES (%s, %s)", (user_msg_id, file_context['file_id']))
             
    except Exception as e:
        print(f"Save error: {e}")

    # 5. Update thread memory (store Q&A for future context)
    try:
        mem_rows = execute_query(
            "SELECT key_facts FROM ai_thread_memory WHERE thread_id = %s",
            (thread_id,)
        ) or []
        key_facts = []
        if mem_rows:
            try:
                key_facts = json.loads(mem_rows[0].get("key_facts") or "[]")
            except Exception:
                pass
        key_facts.append({"q": question[:200], "a": answer[:300]})
        key_facts = key_facts[-10:]  # keep last 10 turns
        kf_json = json.dumps(key_facts, ensure_ascii=False)
        summary = f"آخر سؤال: {question[:100]}"
        execute_query(
            "INSERT INTO ai_thread_memory (thread_id, summary, key_facts) VALUES (%s, %s, %s) "
            "ON DUPLICATE KEY UPDATE summary=%s, key_facts=%s, last_updated=NOW()",
            (thread_id, summary, kf_json, summary, kf_json)
        )
    except Exception as me:
        print(f"Memory save error: {me}")

    sources_used = [c for c in top_chunks if c.get("_score", 0) > 0]
    return {
        "status": "ok",
        "thread_id": thread_id,
        "message_id": asst_msg_id,
        "answer": answer,
        "sources": [{"chunk_id": c["id"], "content": c["content"][:100], "score": c["_score"]} for c in sources_used[:3]],
        "metadata": {
            "latency_ms": latency_ms,
            "input_tokens": len(question.split()),
            "output_tokens": len(answer.split()),
            "sources_found": len(sources_used),
            "model": "local-rag-v1",
            "has_file": bool(file_context),
            "has_memory": bool(memory_context),
            "file_info": file_context.get('filename') if file_context else None,
        }
    }
