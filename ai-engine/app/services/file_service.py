# app/services/file_service.py
"""
خدمة الملفات - رفع، معالجة، تقطيع
"""
import os
import uuid
from app.repositories.file_repo import FileRepository
from app.repositories.document_repo import DocumentRepository
from app.repositories.chunk_repo import ChunkRepository
from app.utils.chunking import chunk_text
from app.utils.text_processing import count_tokens, detect_language
from app.config import settings
from app.core.logging_config import logger


class FileService:
    """خدمة إدارة الملفات"""

    def __init__(self):
        self.file_repo = FileRepository()
        self.doc_repo = DocumentRepository()
        self.chunk_repo = ChunkRepository()
        os.makedirs(settings.UPLOAD_DIR, exist_ok=True)

    async def upload_and_process(self, file_content: bytes, filename: str,
                                  mime_type: str, knowledge_base_id: str = None) -> dict:
        """رفع ملف ومعالجته"""
        # 1. حفظ الملف
        file_ext = os.path.splitext(filename)[1].lower()
        safe_name = f"{uuid.uuid4()}{file_ext}"
        file_path = os.path.join(settings.UPLOAD_DIR, safe_name)

        with open(file_path, "wb") as f:
            f.write(file_content)

        file_size = len(file_content)
        logger.info(f"📁 تم حفظ الملف: {filename} ({file_size} بايت)")

        # 2. استخراج النص
        extracted_text = self._extract_text(file_path, mime_type)

        # 3. حفظ سجل الملف
        file_id = self.file_repo.create(
            filename=filename,
            mime_type=mime_type,
            file_size=file_size,
            file_path=file_path,
            extracted_text=extracted_text,
        )

        # 4. إنشاء مستند وقطع (إذا كان هناك نص)
        chunks_created = 0
        if extracted_text and knowledge_base_id:
            doc_id = self.doc_repo.create(
                knowledge_base_id=knowledge_base_id,
                title=filename,
                file_id=file_id,
                language=detect_language(extracted_text),
            )

            # تقطيع النص
            text_chunks = chunk_text(extracted_text)
            chunk_data = []
            for idx, chunk_content in enumerate(text_chunks):
                chunk_data.append({
                    "document_id": doc_id,
                    "chunk_index": idx + 1,
                    "content": chunk_content,
                    "language": detect_language(chunk_content),
                    "token_count": count_tokens(chunk_content),
                })

            if chunk_data:
                chunks_created = self.chunk_repo.bulk_create(chunk_data)
                logger.info(f"📝 تم إنشاء {chunks_created} قطعة من {filename}")

        return {
            "file_id": file_id,
            "filename": filename,
            "file_size": file_size,
            "mime_type": mime_type,
            "extracted_text_length": len(extracted_text) if extracted_text else 0,
            "chunks_created": chunks_created,
        }

    def _extract_text(self, file_path: str, mime_type: str) -> str:
        """استخراج النص من الملف"""
        try:
            if mime_type == "text/plain" or file_path.endswith(".txt"):
                with open(file_path, "r", encoding="utf-8") as f:
                    return f.read()

            elif mime_type == "text/markdown" or file_path.endswith(".md"):
                with open(file_path, "r", encoding="utf-8") as f:
                    return f.read()

            elif mime_type == "application/pdf" or file_path.endswith(".pdf"):
                return self._extract_pdf_text(file_path)

            else:
                logger.warning(f"⚠️ نوع ملف غير مدعوم لاستخراج النص: {mime_type}")
                return ""

        except Exception as e:
            logger.error(f"❌ خطأ في استخراج النص: {e}")
            return ""

    def _extract_pdf_text(self, file_path: str) -> str:
        """استخراج نص من PDF"""
        try:
            # محاولة استخدام PyPDF2 إذا كان متوفراً
            try:
                import PyPDF2
                text = ""
                with open(file_path, "rb") as f:
                    reader = PyPDF2.PdfReader(f)
                    for page in reader.pages:
                        text += page.extract_text() or ""
                return text
            except ImportError:
                pass

            # محاولة باستخدام pdfplumber
            try:
                import pdfplumber
                text = ""
                with pdfplumber.open(file_path) as pdf:
                    for page in pdf.pages:
                        text += page.extract_text() or ""
                return text
            except ImportError:
                pass

            logger.warning("⚠️ لا توجد مكتبة لقراءة PDF (PyPDF2 أو pdfplumber)")
            return ""

        except Exception as e:
            logger.error(f"❌ خطأ في قراءة PDF: {e}")
            return ""

    def get_file_info(self, file_id: str) -> dict:
        """جلب معلومات ملف"""
        return self.file_repo.get_by_id(file_id)

    def list_files(self, limit: int = 20) -> list:
        """جلب قائمة الملفات"""
        return self.file_repo.list_all(limit)


file_service = FileService()
