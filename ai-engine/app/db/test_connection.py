# app/db/test_connection.py
from mysql_conn import execute_query

result = execute_query("SELECT COUNT(*) AS count FROM ai_sources")
if result is not None:
    print(f"✅ الاتصال ناجح! عدد المصادر: {result[0]['count']}")
else:
    print("❌ فشل الاتصال أو تنفيذ الاستعلام")
