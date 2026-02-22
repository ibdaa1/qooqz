# passenger_wsgi.py
# مسار الملف: /home/hcsfcsto/public_html/ai-engine/passenger_wsgi.py

import sys
import os
from dotenv import load_dotenv

# ===========================================
# 1. إعداد المسارات
# ===========================================
project_home = "/home/hcsfcsto/public_html/ai-engine"

# إضافة مسار المشروع
if project_home not in sys.path:
    sys.path.insert(0, project_home)

# ===========================================
# 2. تحميل متغيرات البيئة
# ===========================================
env_path = os.path.join(project_home, ".env")
if os.path.exists(env_path):
    load_dotenv(env_path)
    print(f"✅ Loaded .env from: {env_path}")
else:
    print(f"⚠️ Warning: .env not found at {env_path}")

# ===========================================
# 3. استيراد وإعداد التطبيق
# ===========================================
try:
    from a2wsgi import ASGIMiddleware
    
    # ❗ التصحيح: main.py في الجذر، ليس في app/
    # استخدم: from main import app
    # وليس: from app.main import app
    from main import app
    
    # تحويل ASGI إلى WSGI
    application = ASGIMiddleware(app)
    
    print("=" * 50)
    print("✅ FastAPI loaded successfully via Passenger WSGI")
    print(f"📂 Project: {project_home}")
    print(f"🐍 Python: {sys.version}")
    print("=" * 50)
    
except ImportError as e:
    error_msg = f"""
    ❌ Import Error: {str(e)}
    
    Possible causes:
    1. a2wsgi not installed: pip3 install --user a2wsgi
    2. main.py not found in {project_home}
    3. FastAPI not installed: pip3 install --user fastapi
    
    Current sys.path:
    {chr(10).join(sys.path)}
    """
    print(error_msg)
    
    # حفظ الخطأ في ملف
    with open(os.path.join(project_home, 'passenger_error.log'), 'w') as f:
        f.write(error_msg)
    
    raise

except Exception as e:
    error_msg = f"""
    ❌ Unexpected Error: {str(e)}
    
    Type: {type(e).__name__}
    
    Check:
    1. main.py syntax errors
    2. Database connection in .env
    3. All required packages installed
    """
    print(error_msg)
    
    # حفظ الخطأ
    with open(os.path.join(project_home, 'passenger_error.log'), 'w') as f:
        f.write(error_msg)
        import traceback
        traceback.print_exc(file=f)
    
    raise

# ===========================================
# 4. معلومات تشخيصية (اختياري)
# ===========================================
if __name__ == '__main__':
    print("\n🔍 Diagnostic Information:")
    print(f"Python executable: {sys.executable}")
    print(f"Python version: {sys.version}")
    print(f"Project home: {project_home}")
    print(f"sys.path: {sys.path}")
    
    # التحقق من المكتبات
    try:
        import fastapi
        print(f"✅ FastAPI version: {fastapi.__version__}")
    except:
        print("❌ FastAPI not found")
    
    try:
        import a2wsgi
        print("✅ a2wsgi installed")
    except:
        print("❌ a2wsgi not found")
    
    try:
        import mysql.connector
        print("✅ mysql-connector-python installed")
    except:
        print("❌ mysql-connector-python not found")