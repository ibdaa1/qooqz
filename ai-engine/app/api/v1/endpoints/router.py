# ~/public_html/ai-engine/app/api/v1/router.py
from fastapi import APIRouter
from app.api.v1.endpoints import health  # استيراد endpoint الصحة

# تعريف router رئيسي للإصدار v1
api_v1_router = APIRouter()
api_v1_router.include_router(health.router, prefix="/api/v1")
