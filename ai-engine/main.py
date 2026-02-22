# main.py (root)
"""
نقطة الدخول الرئيسية - يسجل الـ routers مباشرة مثل health
"""
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

# ====== إنشاء التطبيق ======
app = FastAPI(
    title="RAG System API",
    description="نظام RAG للرد على الأسئلة",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

# ====== CORS ======
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ====== تسجيل الـ Routers ======

# 1. Health (موجود ويعمل)
try:
    from app.api.v1.endpoints import health
    app.include_router(health.router, prefix="/api/v1", tags=["health"])
    print("✅ Health router OK")
except ImportError as e:
    print(f"⚠️ Health router: {e}")

# 2. Chat (جديد - بنفس أسلوب health)
try:
    from app.api.v1.endpoints import chat
    app.include_router(chat.router, prefix="/api/v1", tags=["chat"])
    print("✅ Chat router OK")
except ImportError as e:
    print(f"⚠️ Chat router: {e}")

# 3. Questions (موجود)
try:
    from app.api.v1.endpoints import questions
    app.include_router(questions.router, prefix="/api/v1", tags=["questions"])
    print("✅ Questions router OK")
except ImportError as e:
    print(f"⚠️ Questions router: {e}")

# 4. Threads
try:
    from app.api.v1.endpoints import threads
    app.include_router(threads.router, prefix="/api/v1", tags=["threads"])
    print("✅ Threads router OK")
except ImportError as e:
    print(f"⚠️ Threads router: {e}")

# 5. Knowledge
try:
    from app.api.v1.endpoints import knowledge
    app.include_router(knowledge.router, prefix="/api/v1", tags=["knowledge"])
    print("✅ Knowledge router OK")
except ImportError as e:
    print(f"⚠️ Knowledge router: {e}")

# 6. Files
try:
    from app.api.v1.endpoints import files
    app.include_router(files.router, prefix="/api/v1", tags=["files"])
    print("✅ Files router OK")
except ImportError as e:
    print(f"⚠️ Files router: {e}")

# 7. Feedback
try:
    from app.api.v1.endpoints import feedback
    app.include_router(feedback.router, prefix="/api/v1", tags=["feedback"])
    print("✅ Feedback router OK")
except ImportError as e:
    print(f"⚠️ Feedback router: {e}")


# ====== الصفحة الرئيسية ======
@app.get("/")
def root():
    return {
        "message": "🤖 RAG System API",
        "version": "1.0.0",
        "status": "running",
        "endpoints": {
            "docs": "/docs",
            "health": "/api/v1/health",
            "chat": "POST /api/v1/chat",
            "threads": "/api/v1/threads",
        },
    }


@app.get("/ping")
def ping():
    return {"status": "ok", "message": "pong"}


@app.on_event("startup")
async def startup():
    print("\n" + "=" * 60)
    print("🚀 FastAPI RAG System يعمل!")
    print("=" * 60)
    print("📖 Docs:    /docs")
    print("🔍 Health:  /api/v1/health")
    print("💬 Chat:    POST /api/v1/chat")
    print("=" * 60 + "\n")


@app.on_event("shutdown")
async def shutdown():
    print("\n🛑 إيقاف FastAPI...\n")


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8888, reload=True)
