# app/utils/file_processor.py
"""
🔍 معالج الملفات الشامل
يستخرج النصوص من: صور (OCR)، PDF، TXT، CSV، DOCX
"""
import os
import io
import csv
import json
import struct
import zipfile
import re


def extract_text_from_file(file_path: str, mime_type: str = "", file_bytes: bytes = None) -> dict:
    """
    استخراج النص من أي ملف
    يعيد: {"text": str, "method": str, "metadata": dict, "success": bool}
    """
    result = {"text": "", "method": "none", "metadata": {}, "success": False}

    try:
        ext = os.path.splitext(file_path)[1].lower() if file_path else ""

        # قراءة الملف إذا لم يُعطَ كـ bytes
        if file_bytes is None and file_path and os.path.exists(file_path):
            with open(file_path, "rb") as f:
                file_bytes = f.read()

        if not file_bytes:
            result["text"] = "لم يتم العثور على الملف أو أنه فارغ"
            return result

        result["metadata"]["file_size"] = len(file_bytes)
        result["metadata"]["extension"] = ext

        # ====== معالجة حسب النوع ======

        # 1. ملفات نصية
        if ext in [".txt", ".text", ".md", ".log", ".ini", ".cfg", ".json", ".xml", ".html", ".htm", ".css", ".js", ".py", ".php", ".sql", ".yml", ".yaml", ".env"]:
            result = _process_text_file(file_bytes, ext, result)

        # 2. CSV
        elif ext == ".csv":
            result = _process_csv_file(file_bytes, result)

        # 3. PDF
        elif ext == ".pdf":
            result = _process_pdf_file(file_bytes, result)

        # 4. DOCX (Word)
        elif ext in [".docx"]:
            result = _process_docx_file(file_bytes, result)

        # 5. صور
        elif ext in [".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp", ".tiff", ".tif", ".svg"]:
            result = _process_image_file(file_bytes, ext, file_path, result)

        # 6. أنواع أخرى
        else:
            # محاولة قراءة كنص
            try:
                text = file_bytes.decode("utf-8", errors="ignore")
                if text.strip() and _is_readable_text(text):
                    result["text"] = text.strip()
                    result["method"] = "raw_decode"
                    result["success"] = True
                else:
                    result["text"] = f"نوع الملف ({ext}) غير مدعوم للقراءة التلقائية"
                    result["method"] = "unsupported"
            except:
                result["text"] = f"نوع الملف ({ext}) غير مدعوم"
                result["method"] = "unsupported"

    except Exception as e:
        result["text"] = f"خطأ في معالجة الملف: {str(e)}"
        result["method"] = "error"

    return result


def _is_readable_text(text: str) -> bool:
    """تحقق إذا النص قابل للقراءة"""
    if not text:
        return False
    # إذا أكثر من 30% أحرف غير قابلة للطباعة، ليس نصاً
    non_printable = sum(1 for c in text[:500] if ord(c) < 32 and c not in '\n\r\t')
    return non_printable / max(len(text[:500]), 1) < 0.3


def _process_text_file(file_bytes: bytes, ext: str, result: dict) -> dict:
    """معالجة ملف نصي"""
    # محاولة UTF-8 أولاً، ثم windows-1256 للعربية
    for encoding in ["utf-8", "utf-8-sig", "windows-1256", "iso-8859-6", "cp1252", "latin-1"]:
        try:
            text = file_bytes.decode(encoding)
            result["text"] = text.strip()
            result["method"] = f"text_{encoding}"
            result["metadata"]["encoding"] = encoding
            result["metadata"]["line_count"] = text.count("\n") + 1
            result["metadata"]["word_count"] = len(text.split())
            result["success"] = True

            # JSON خاص
            if ext == ".json":
                try:
                    data = json.loads(text)
                    result["text"] = json.dumps(data, ensure_ascii=False, indent=2)
                    result["method"] = "json_parsed"
                except:
                    pass

            return result
        except (UnicodeDecodeError, UnicodeError):
            continue

    result["text"] = "تعذر قراءة الملف النصي"
    return result


def _process_csv_file(file_bytes: bytes, result: dict) -> dict:
    """معالجة ملف CSV"""
    try:
        text = file_bytes.decode("utf-8", errors="ignore")
        reader = csv.reader(io.StringIO(text))
        rows = list(reader)

        if not rows:
            result["text"] = "ملف CSV فارغ"
            return result

        # أول صف كعناوين
        headers = rows[0]
        result["metadata"]["columns"] = headers
        result["metadata"]["row_count"] = len(rows) - 1

        # تحويل إلى نص مقروء
        output_lines = [f"الأعمدة: {', '.join(headers)}"]
        output_lines.append(f"عدد الصفوف: {len(rows) - 1}")
        output_lines.append("")

        for i, row in enumerate(rows[1:51], 1):  # أول 50 صف
            row_data = {headers[j]: row[j] for j in range(min(len(headers), len(row)))}
            output_lines.append(f"صف {i}: {json.dumps(row_data, ensure_ascii=False)}")

        if len(rows) > 51:
            output_lines.append(f"... و {len(rows) - 51} صفوف أخرى")

        result["text"] = "\n".join(output_lines)
        result["method"] = "csv_parsed"
        result["success"] = True

    except Exception as e:
        result["text"] = f"خطأ في قراءة CSV: {e}"
        result["method"] = "csv_error"

    return result


def _process_pdf_file(file_bytes: bytes, result: dict) -> dict:
    """معالجة ملف PDF - استخراج نص بدون مكتبات خارجية"""

    # محاولة 1: PyPDF2 (إذا متوفر)
    try:
        import PyPDF2
        reader = PyPDF2.PdfReader(io.BytesIO(file_bytes))
        pages_text = []
        for i, page in enumerate(reader.pages):
            text = page.extract_text()
            if text and text.strip():
                pages_text.append(f"--- صفحة {i + 1} ---\n{text.strip()}")

        if pages_text:
            result["text"] = "\n\n".join(pages_text)
            result["method"] = "pypdf2"
            result["metadata"]["page_count"] = len(reader.pages)
            result["metadata"]["pages_with_text"] = len(pages_text)
            result["success"] = True
            return result
    except ImportError:
        pass
    except Exception:
        pass

    # محاولة 2: pdfminer (إذا متوفر)
    try:
        from pdfminer.high_level import extract_text as pdfminer_extract
        text = pdfminer_extract(io.BytesIO(file_bytes))
        if text and text.strip():
            result["text"] = text.strip()
            result["method"] = "pdfminer"
            result["success"] = True
            return result
    except ImportError:
        pass
    except Exception:
        pass

    # محاولة 3: استخراج بدائي من PDF binary
    try:
        text = _extract_pdf_raw(file_bytes)
        if text and len(text.strip()) > 20:
            result["text"] = text.strip()
            result["method"] = "pdf_raw"
            result["success"] = True
            return result
    except:
        pass

    result["text"] = "ملف PDF - لتحليله يُرجى تثبيت: pip install PyPDF2"
    result["method"] = "pdf_no_lib"
    result["metadata"]["is_pdf"] = True
    return result


def _extract_pdf_raw(data: bytes) -> str:
    """استخراج نص خام من PDF بدون مكتبات"""
    text_parts = []

    # البحث عن streams نصية
    content = data.decode("latin-1", errors="ignore")

    # استخراج نصوص بين أقواس
    for match in re.finditer(r'\(([^)]{3,500})\)', content):
        t = match.group(1)
        # تحقق أنه نص مقروء
        if any(ord(c) > 127 for c in t):  # يحتوي أحرف غير ASCII (عربي مثلاً)
            text_parts.append(t)
        elif t.strip() and all(c.isprintable() or c in '\n\r\t ' for c in t):
            text_parts.append(t)

    # البحث عن BT...ET blocks
    for match in re.finditer(r'BT\s*(.*?)\s*ET', content, re.DOTALL):
        block = match.group(1)
        for text_match in re.finditer(r'\(([^)]+)\)', block):
            text_parts.append(text_match.group(1))

    return "\n".join(text_parts[:100])


def _process_docx_file(file_bytes: bytes, result: dict) -> dict:
    """معالجة ملف Word DOCX"""
    try:
        # DOCX هو ZIP يحتوي على XML
        with zipfile.ZipFile(io.BytesIO(file_bytes)) as zf:
            # قراءة document.xml
            if "word/document.xml" in zf.namelist():
                xml_content = zf.read("word/document.xml").decode("utf-8")
                # استخراج النص من XML
                text = re.sub(r'<[^>]+>', ' ', xml_content)
                text = re.sub(r'\s+', ' ', text).strip()

                # تحسين الفقرات
                paragraphs = [p.strip() for p in text.split('  ') if len(p.strip()) > 2]

                result["text"] = "\n".join(paragraphs)
                result["method"] = "docx_xml"
                result["metadata"]["word_count"] = len(result["text"].split())
                result["success"] = True
            else:
                result["text"] = "ملف DOCX لا يحتوي على محتوى نصي"
    except Exception as e:
        result["text"] = f"خطأ في قراءة DOCX: {e}"
        result["method"] = "docx_error"

    return result


def _process_image_file(file_bytes: bytes, ext: str, file_path: str, result: dict) -> dict:
    """معالجة صورة - معلومات + OCR"""

    result["metadata"]["type"] = "image"

    # 1. معلومات الصورة الأساسية
    image_info = _get_image_info(file_bytes, ext)
    result["metadata"].update(image_info)

    # 2. محاولة OCR مع Pillow + pytesseract
    ocr_text = ""

    # محاولة 1: pytesseract
    try:
        from PIL import Image
        import pytesseract

        img = Image.open(io.BytesIO(file_bytes))
        result["metadata"]["width"] = img.width
        result["metadata"]["height"] = img.height
        result["metadata"]["format"] = img.format
        result["metadata"]["mode"] = img.mode

        # OCR مع دعم العربية والإنجليزية
        try:
            ocr_text = pytesseract.image_to_string(img, lang="ara+eng")
        except:
            try:
                ocr_text = pytesseract.image_to_string(img, lang="eng")
            except:
                try:
                    ocr_text = pytesseract.image_to_string(img)
                except:
                    pass

        if ocr_text and ocr_text.strip():
            result["text"] = f"📷 نص مُستخرج من الصورة:\n\n{ocr_text.strip()}"
            result["method"] = "ocr_pytesseract"
            result["success"] = True
            result["metadata"]["ocr_chars"] = len(ocr_text.strip())
            return result

    except ImportError:
        pass
    except Exception as e:
        result["metadata"]["ocr_error"] = str(e)

    # محاولة 2: Pillow فقط (بدون OCR)
    try:
        from PIL import Image
        img = Image.open(io.BytesIO(file_bytes))
        result["metadata"]["width"] = img.width
        result["metadata"]["height"] = img.height
        result["metadata"]["format"] = img.format
        result["metadata"]["mode"] = img.mode

        # تحليل الألوان السائدة
        try:
            img_small = img.copy()
            img_small.thumbnail((100, 100))
            if img_small.mode != "RGB":
                img_small = img_small.convert("RGB")
            colors = img_small.getcolors(maxcolors=1000)
            if colors:
                colors.sort(key=lambda x: x[0], reverse=True)
                top_colors = colors[:5]
                result["metadata"]["dominant_colors"] = [
                    {"count": c, "rgb": list(rgb)} for c, rgb in top_colors
                ]
        except:
            pass

        description = (
            f"📷 صورة: {img.width}×{img.height} بكسل\n"
            f"النوع: {img.format or ext.upper()}\n"
            f"الوضع: {img.mode}\n"
            f"الحجم: {len(file_bytes) / 1024:.1f} KB\n\n"
            f"⚠️ لاستخراج النص من الصورة، يُرجى تثبيت:\n"
            f"pip install pytesseract\n"
            f"وتثبيت Tesseract OCR على النظام"
        )
        result["text"] = description
        result["method"] = "pillow_info"
        result["success"] = True
        return result

    except ImportError:
        pass

    # محاولة 3: بدون أي مكتبة - معلومات أساسية من الـ header
    info = _get_image_info(file_bytes, ext)
    description = (
        f"📷 صورة ({ext})\n"
        f"الحجم: {len(file_bytes) / 1024:.1f} KB\n"
    )
    if info.get("width"):
        description += f"الأبعاد: {info['width']}×{info['height']}\n"

    description += "\n⚠️ لتحليل أعمق، ثبّت: pip install Pillow pytesseract"

    result["text"] = description
    result["method"] = "basic_info"
    result["success"] = True
    return result


def _get_image_info(data: bytes, ext: str) -> dict:
    """استخراج أبعاد الصورة من الـ header بدون مكتبات"""
    info = {}
    try:
        if ext in [".jpg", ".jpeg"]:
            # JPEG SOF marker
            i = 2
            while i < len(data) - 8:
                if data[i] == 0xFF:
                    marker = data[i + 1]
                    if marker in (0xC0, 0xC1, 0xC2):
                        info["height"] = struct.unpack(">H", data[i + 5:i + 7])[0]
                        info["width"] = struct.unpack(">H", data[i + 7:i + 9])[0]
                        break
                    elif marker == 0xD9:
                        break
                    else:
                        size = struct.unpack(">H", data[i + 2:i + 4])[0]
                        i += size + 2
                else:
                    i += 1

        elif ext == ".png":
            if data[:4] == b'\x89PNG':
                info["width"] = struct.unpack(">I", data[16:20])[0]
                info["height"] = struct.unpack(">I", data[20:24])[0]

        elif ext == ".gif":
            if data[:3] in (b'GIF', b'GIF'):
                info["width"] = struct.unpack("<H", data[6:8])[0]
                info["height"] = struct.unpack("<H", data[8:10])[0]

        elif ext == ".bmp":
            if data[:2] == b'BM':
                info["width"] = struct.unpack("<I", data[18:22])[0]
                info["height"] = abs(struct.unpack("<i", data[22:26])[0])

    except:
        pass

    return info


def summarize_extracted_text(text: str, max_length: int = 2000) -> str:
    """تلخيص النص المستخرج إذا كان طويلاً"""
    if not text:
        return ""
    if len(text) <= max_length:
        return text

    # أخذ البداية والنهاية
    half = max_length // 2
    return text[:half] + f"\n\n... [تم اختصار {len(text) - max_length} حرف] ...\n\n" + text[-half:]
