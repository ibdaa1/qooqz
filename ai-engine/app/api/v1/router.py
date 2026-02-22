# app/api/v1/router.py
"""
الموجه الرئيسي لإصدار API v1 - يسجل كل الـ endpoints
"""
from fastapi import APIRouter
from app.api.v1.endpoints import health, chat, threads, files, knowledge, feedback
from app.api.v1.endpoints import questions

# الموجه الرئيسي
api_v1_router = APIRouter()

# تسجيل كل الـ routers
api_v1_router.include_router(health.router, tags=["health"])
api_v1_router.include_router(chat.router, tags=["chat"])
api_v1_router.include_router(threads.router, tags=["threads"])
api_v1_router.include_router(files.router, tags=["files"])
api_v1_router.include_router(knowledge.router, tags=["knowledge"])
api_v1_router.include_router(feedback.router, tags=["feedback"])
api_v1_router.include_router(questions.router, tags=["questions"])
