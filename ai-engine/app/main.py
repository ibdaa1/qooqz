# app/main.py
"""
RAG System API - نقطة البداية الرئيسية
نظام ذكاء اصطناعي كامل مع RAG، ذاكرة، تحليل صور
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.core.logging_config import logger
from app.db.base import init_pool, close_pool

# إنشاء تطبيق FastAPI
app = FastAPI(
    title="🤖 AI RAG System API",
    description="نظام ذكاء اصطناعي للرد على الأسئلة باستخدام RAG مع ذاكرة وتحليل صور",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

# إعداد CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# تسجيل كل الـ routers
try:
    from app.api.v1.router import api_v1_router
    app.include_router(api_v1_router, prefix="/api/v1")
    logger.info("✅ تم تسجيل كل الـ API routers")
except ImportError as e:
    logger.error(f"❌ خطأ في تسجيل الـ routers: {e}")


# الصفحة الرئيسية
@app.get("/")
def root():
    """الصفحة الرئيسية للـ API"""
    return {
        "message": "🤖 AI RAG System API",
        "version": "1.0.0",
        "status": "running",
        "endpoints": {
            "docs": "/docs",
            "health": "/api/v1/health",
            "chat": "POST /api/v1/chat",
            "chat_json": "POST /api/v1/chat/json",
            "chat_image": "POST /api/v1/chat/with-image",
            "threads": "/api/v1/threads",
            "files": "/api/v1/files",
            "knowledge": "/api/v1/knowledge-bases",
            "feedback": "/api/v1/feedback",
            "questions": "/api/v1/questions",
        },
    }


@app.get("/ping")
def ping():
    """اختبار بسيط"""
    return {"status": "ok", "message": "pong"}


# عند بدء التشغيل
@app.on_event("startup")
async def startup_event():
    """تهيئة النظام عند البدء"""
    logger.info("=" * 60)
    logger.info("🚀 AI RAG System يعمل الآن!")
    logger.info("=" * 60)

    # تهيئة تجمع الاتصالات
    pool_ok = init_pool()
    if pool_ok:
        logger.info("✅ تجمع قاعدة البيانات جاهز")
    else:
        logger.warning("⚠️ فشل تهيئة تجمع قاعدة البيانات - سيستخدم اتصال مباشر")

    logger.info("📖 API Docs: /docs")
    logger.info("🔍 Health: /api/v1/health")
    logger.info("💬 Chat: POST /api/v1/chat")
    logger.info("=" * 60)


# عند الإيقاف
@app.on_event("shutdown")
async def shutdown_event():
    """تنظيف عند الإيقاف"""
    close_pool()
    logger.info("🛑 تم إيقاف AI RAG System")