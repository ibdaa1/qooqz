# app/services/vision_service.py
"""
خدمة تحليل الصور - OCR ومعلومات الصورة
"""
import os
from app.repositories.vision_repo import VisionRepository
from app.repositories.file_repo import FileRepository
from app.core.logging_config import logger


class VisionService:
    """تحليل الصور والتعرف على النصوص"""

    def __init__(self):
        self.vision_repo = VisionRepository()
        self.file_repo = FileRepository()

    def analyze_image(self, file_id: str, file_path: str,
                      message_id: str = None) -> dict:
        """
        تحليل صورة واستخراج المعلومات
        
        يحاول استخدام:
        1. Pillow لمعلومات الصورة
        2. pytesseract للـ OCR (إذا كان متوفراً)
        """
        result = {
            "extracted_text": "",
            "description": "",
            "structured_data": {},
        }

        try:
            from PIL import Image

            if not os.path.exists(file_path):
                logger.error(f"❌ الصورة غير موجودة: {file_path}")
                return result

            img = Image.open(file_path)

            # معلومات الصورة
            result["structured_data"] = {
                "width": img.width,
                "height": img.height,
                "format": img.format,
                "mode": img.mode,
                "size_bytes": os.path.getsize(file_path),
            }

            result["description"] = (
                f"صورة بتنسيق {img.format or 'غير معروف'} "
                f"بأبعاد {img.width}x{img.height} بكسل"
            )

            # محاولة OCR
            try:
                import pytesseract
                text = pytesseract.image_to_string(img, lang='ara+eng')
                if text and text.strip():
                    result["extracted_text"] = text.strip()
                    result["description"] += f". تم استخراج نص ({len(text.split())} كلمة)"
                    logger.info(f"✅ OCR: تم استخراج {len(text.split())} كلمة")
            except ImportError:
                logger.warning("⚠️ pytesseract غير مثبت - تخطي OCR")
            except Exception as ocr_err:
                logger.warning(f"⚠️ خطأ OCR: {ocr_err}")

        except ImportError:
            logger.warning("⚠️ Pillow غير مثبت")
            result["description"] = "لم يتمكن من تحليل الصورة (Pillow غير مثبت)"
        except Exception as e:
            logger.error(f"❌ خطأ في تحليل الصورة: {e}")
            result["description"] = f"خطأ في تحليل الصورة: {str(e)}"

        # حفظ في قاعدة البيانات
        try:
            vision_id = self.vision_repo.create(
                file_id=file_id,
                extracted_text=result["extracted_text"],
                description=result["description"],
                structured_data=result["structured_data"],
                message_id=message_id,
            )
            result["vision_id"] = vision_id
        except Exception as e:
            logger.error(f"❌ خطأ في حفظ تحليل الصورة: {e}")

        return result

    def get_analysis(self, file_id: str) -> dict:
        """جلب تحليل سابق"""
        return self.vision_repo.get_by_file(file_id)


vision_service = VisionService()
