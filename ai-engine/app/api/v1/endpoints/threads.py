# app/api/v1/endpoints/threads.py
"""
نقاط نهاية المحادثات - تعمل مباشرة مع mysql_conn.py
"""
from fastapi import APIRouter, HTTPException
from app.db.mysql_conn import execute_query

router = APIRouter()


@router.get("/threads")
def list_threads(limit: int = 20, offset: int = 0):
    """قائمة المحادثات"""
    try:
        threads = execute_query(
            """SELECT id, title, created_at, updated_at
               FROM ai_threads
               ORDER BY updated_at DESC
               LIMIT %s OFFSET %s""",
            (limit, offset)
        ) or []

        # تحويل datetime إلى string
        for t in threads:
            for key in ["created_at", "updated_at"]:
                if t.get(key):
                    t[key] = str(t[key])

        count_result = execute_query("SELECT COUNT(*) as total FROM ai_threads")
        total = count_result[0]["total"] if count_result else 0

        return {"status": "ok", "threads": threads, "total": total}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/threads/{thread_id}")
def get_thread(thread_id: str):
    """جلب محادثة مع رسائلها"""
    try:
        thread_rows = execute_query(
            "SELECT id, title, created_at, updated_at FROM ai_threads WHERE id = %s",
            (thread_id,)
        )
        if not thread_rows:
            raise HTTPException(status_code=404, detail="المحادثة غير موجودة")

        thread = thread_rows[0]
        for key in ["created_at", "updated_at"]:
            if thread.get(key):
                thread[key] = str(thread[key])

        messages = execute_query(
            """SELECT id, thread_id, role, content, model, tokens, latency_ms, language, created_at
               FROM ai_messages
               WHERE thread_id = %s
               ORDER BY created_at ASC""",
            (thread_id,)
        ) or []

        for msg in messages:
            if msg.get("created_at"):
                msg["created_at"] = str(msg["created_at"])

        return {
            "status": "ok",
            "thread": thread,
            "messages": messages,
            "message_count": len(messages),
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.delete("/threads/{thread_id}")
def delete_thread(thread_id: str):
    """حذف محادثة"""
    try:
        existing = execute_query(
            "SELECT id FROM ai_threads WHERE id = %s", (thread_id,)
        )
        if not existing:
            raise HTTPException(status_code=404, detail="المحادثة غير موجودة")

        execute_query("DELETE FROM ai_messages WHERE thread_id = %s", (thread_id,))
        execute_query("DELETE FROM ai_thread_memory WHERE thread_id = %s", (thread_id,))
        execute_query("DELETE FROM ai_threads WHERE id = %s", (thread_id,))

        return {"status": "ok", "message": "تم حذف المحادثة"}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
