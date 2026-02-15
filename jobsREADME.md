# 📋 Jobs Backend API - دليل الاستخدام الشامل

نظام إدارة الوظائف الاحترافي مع دعم متعدد اللغات.

---

## 📁 هيكل الملفات

```
jobs-backend/
├── repositories/
│   └── PdoJobsRepository.php      # طبقة الوصول للبيانات
├── validators/
│   └── JobsValidator.php          # التحقق من صحة البيانات
├── services/
│   └── JobsService.php            # منطق الأعمال
├── controllers/
│   └── JobsController.php         # التحكم بالطلبات
└── api.php                        # نقطة الدخول الرئيسية
```

---

## 🚀 الميزات الرئيسية

- ✅ دعم متعدد اللغات (Multilingual)
- ✅ فلترة وبحث متقدم
- ✅ ترتيب ديناميكي
- ✅ Pagination
- ✅ إدارة الترجمات
- ✅ تتبع المشاهدات والتقديمات
- ✅ إدارة حالات الوظائف
- ✅ وظائف مميزة وعاجلة
- ✅ دعم العمل عن بُعد

---

## 📡 API Endpoints

### 1. قائمة الوظائف (List Jobs)

**الطلب:**
```http
GET /api/jobs.php?page=1&limit=25&lang=ar
```

**المعاملات (Query Parameters):**

| المعامل | النوع | الافتراضي | الوصف |
|---------|------|-----------|-------|
| `page` | integer | 1 | رقم الصفحة |
| `limit` | integer | 25 | عدد النتائج لكل صفحة (max: 1000) |
| `lang` | string | 'ar' | كود اللغة |
| `order_by` | string | 'id' | الترتيب حسب |
| `order_dir` | string | 'DESC' | اتجاه الترتيب (ASC/DESC) |

**الفلاتر المتاحة:**
```http
GET /api/jobs.php?entity_id=123&job_type=full_time&status=published&is_featured=1
```

| الفلتر | النوع | الوصف |
|--------|------|-------|
| `entity_id` | integer | معرف الكيان (الشركة) |
| `job_type` | string | نوع الوظيفة |
| `employment_type` | string | نوع العمل |
| `experience_level` | string | مستوى الخبرة |
| `category` | string | الفئة |
| `department` | string | القسم |
| `country_id` | integer | معرف الدولة |
| `city_id` | integer | معرف المدينة |
| `is_remote` | integer | عمل عن بُعد (0/1) |
| `status` | string | الحالة |
| `is_featured` | integer | مميز (0/1) |
| `is_urgent` | integer | عاجل (0/1) |
| `salary_negotiable` | integer | راتب قابل للتفاوض (0/1) |
| `search` | string | كلمة البحث |
| `salary_min` | decimal | الحد الأدنى للراتب |
| `salary_max` | decimal | الحد الأقصى للراتب |
| `deadline_after` | datetime | آخر موعد للتقديم بعد |

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "entity_id": 123,
        "job_title": "مطور Full Stack",
        "slug": "full-stack-developer-123",
        "job_type": "full_time",
        "employment_type": "permanent",
        "experience_level": "mid",
        "category": "تقنية المعلومات",
        "department": "التطوير",
        "positions_available": 2,
        "salary_min": "8000.00",
        "salary_max": "12000.00",
        "salary_currency": "SAR",
        "salary_period": "monthly",
        "salary_negotiable": 0,
        "country_id": 1,
        "city_id": 5,
        "work_location": "الرياض - حي العليا",
        "is_remote": 0,
        "status": "published",
        "application_deadline": "2026-03-15 23:59:59",
        "start_date": "2026-04-01",
        "views_count": 156,
        "applications_count": 12,
        "is_featured": 1,
        "is_urgent": 0,
        "description": "نبحث عن مطور Full Stack متمرس...",
        "requirements": "- خبرة 3-5 سنوات...",
        "responsibilities": "- تطوير تطبيقات ويب...",
        "benefits": "- راتب تنافسي...",
        "created_at": "2026-02-10 10:00:00",
        "published_at": "2026-02-10 14:00:00"
      }
    ],
    "meta": {
      "total": 45,
      "page": 1,
      "per_page": 25,
      "total_pages": 2,
      "from": 1,
      "to": 25
    }
  }
}
```

---

### 2. وظيفة واحدة (Get Single Job)

**بواسطة ID:**
```http
GET /api/jobs.php?id=1&lang=ar
```

**بواسطة Slug:**
```http
GET /api/jobs.php?slug=full-stack-developer-123&lang=ar
```

**مع الترجمات:**
```http
GET /api/jobs.php?id=1&with_translations=1&lang=ar
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "job_title": "مطور Full Stack",
    "description": "...",
    "translations": [
      {
        "id": 1,
        "job_id": 1,
        "language_code": "ar",
        "job_title": "مطور Full Stack",
        "description": "...",
        "language_name": "Arabic",
        "language_direction": "rtl"
      },
      {
        "id": 2,
        "job_id": 1,
        "language_code": "en",
        "job_title": "Full Stack Developer",
        "description": "...",
        "language_name": "English",
        "language_direction": "ltr"
      }
    ]
  }
}
```

---

### 3. إنشاء وظيفة جديدة (Create Job)

**الطلب:**
```http
POST /api/jobs.php?lang=ar
Content-Type: application/json
```

**البيانات المطلوبة:**
```json
{
  "entity_id": 123,
  "job_title": "مطور Full Stack",
  "description": "نبحث عن مطور Full Stack متمرس للانضمام لفريقنا...",
  "job_type": "full_time",
  "employment_type": "permanent",
  "experience_level": "mid",
  "country_id": 1,
  "city_id": 5,
  "work_location": "الرياض - حي العليا",
  "category": "تقنية المعلومات",
  "department": "التطوير",
  "positions_available": 2,
  "salary_min": 8000.00,
  "salary_max": 12000.00,
  "salary_currency": "SAR",
  "salary_period": "monthly",
  "application_deadline": "2026-03-15 23:59:59",
  "start_date": "2026-04-01",
  "requirements": "- خبرة 3-5 سنوات في تطوير Full Stack\n- إتقان React و Node.js",
  "responsibilities": "- تطوير تطبيقات ويب متكاملة\n- العمل مع فريق التطوير",
  "benefits": "- راتب تنافسي\n- تأمين طبي\n- بدل سكن",
  "is_featured": 1,
  "is_urgent": 0,
  "is_remote": 0,
  "status": "draft"
}
```

**الحقول الاختيارية:**
- `slug`: سيتم توليده تلقائياً إذا لم يُحدد
- `employment_type`: افتراضياً `permanent`
- `application_form_type`: افتراضياً `simple`
- `salary_negotiable`: افتراضياً `0`
- `positions_available`: افتراضياً `1`
- `views_count`: افتراضياً `0`
- `applications_count`: افتراضياً `0`

**أنواع الوظائف المتاحة (`job_type`):**
- `full_time` - دوام كامل
- `part_time` - دوام جزئي
- `contract` - عقد
- `temporary` - مؤقت
- `internship` - تدريب
- `freelance` - عمل حر
- `remote` - عن بُعد

**مستويات الخبرة (`experience_level`):**
- `entry` - مبتدئ
- `junior` - مبتدئ متقدم
- `mid` - متوسط
- `senior` - خبير
- `executive` - تنفيذي
- `director` - مدير

**حالات الوظيفة (`status`):**
- `draft` - مسودة
- `published` - منشور
- `closed` - مغلق
- `filled` - تم شغله
- `cancelled` - ملغى

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 5
  },
  "message": "Job created successfully"
}
```

---

### 4. تحديث وظيفة (Update Job)

**الطلب:**
```http
PUT /api/jobs.php?lang=ar
Content-Type: application/json
```

**البيانات:**
```json
{
  "id": 5,
  "job_title": "مطور Full Stack Senior",
  "description": "تحديث الوصف...",
  "salary_min": 10000.00,
  "salary_max": 15000.00,
  "status": "published"
}
```

**ملاحظة:** يتم تحديث الحقول المرسلة فقط.

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 5
  },
  "message": "Job updated successfully"
}
```

---

### 5. حذف وظيفة (Delete Job)

**الطلب:**
```http
DELETE /api/jobs.php
Content-Type: application/json
```

**البيانات:**
```json
{
  "id": 5
}
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "deleted": true
  },
  "message": "Job deleted successfully"
}
```

---

### 6. تحديث حالة الوظيفة (Update Status)

**تحديث الحالة:**
```http
PATCH /api/jobs.php?id=5
Content-Type: application/json

{
  "status": "published"
}
```

**نشر وظيفة:**
```http
PATCH /api/jobs.php?id=5&action=publish
```

**إغلاق وظيفة:**
```http
PATCH /api/jobs.php?id=5&action=close
```

**تحديد كـ "تم شغلها":**
```http
PATCH /api/jobs.php?id=5&action=filled
```

**إلغاء وظيفة:**
```http
PATCH /api/jobs.php?id=5&action=cancel
```

**زيادة عدد التقديمات:**
```http
PATCH /api/jobs.php?id=5&action=increment_applications
```

---

### 7. إدارة الترجمات (Translations)

**الحصول على جميع الترجمات:**
```http
GET /api/jobs.php?job_id=5&translations=1
```

**حفظ/تحديث ترجمة:**
```http
PATCH /api/jobs.php?job_id=5&translation=1
Content-Type: application/json

{
  "language_code": "en",
  "job_title": "Full Stack Developer",
  "description": "We are looking for an experienced Full Stack Developer...",
  "requirements": "- 3-5 years of Full Stack development experience",
  "responsibilities": "- Develop full-stack web applications",
  "benefits": "- Competitive salary\n- Health insurance"
}
```

**حذف ترجمة:**
```http
PATCH /api/jobs.php?job_id=5&delete_translation=1&lang_code=en
```

---

### 8. البحث والفلاتر الخاصة

**البحث بكلمة مفتاحية:**
```http
GET /api/jobs.php?search=مطور&lang=ar
```
أو:
```http
GET /api/jobs.php?q=developer&lang=en
```

**الوظائف المميزة:**
```http
GET /api/jobs.php?featured=1&featured_limit=10&lang=ar
```

**الوظائف العاجلة:**
```http
GET /api/jobs.php?urgent=1&urgent_limit=10&lang=ar
```

**الوظائف عن بُعد:**
```http
GET /api/jobs.php?remote=1&remote_limit=10&lang=ar
```

**فلتر حسب نطاق الراتب:**
```http
GET /api/jobs.php?salary_min=5000&salary_max=15000&lang=ar
```

**فلتر حسب تاريخ انتهاء التقديم:**
```http
GET /api/jobs.php?deadline_after=2026-03-01&lang=ar
```

**فلاتر مجمعة:**
```http
GET /api/jobs.php?job_type=full_time&experience_level=mid&is_remote=1&country_id=1&status=published&lang=ar
```

---

## 📊 أمثلة الاستخدام بـ JavaScript

### 1. الحصول على قائمة الوظائف

```javascript
async function getJobs(page = 1, filters = {}) {
  const params = new URLSearchParams({
    page: page,
    limit: 25,
    lang: 'ar',
    ...filters
  });

  const response = await fetch(`/api/jobs.php?${params}`);
  const data = await response.json();
  
  if (data.success) {
    console.log('Jobs:', data.data.items);
    console.log('Total:', data.data.meta.total);
  }
}

// استخدام
getJobs(1, { 
  job_type: 'full_time', 
  is_featured: 1,
  status: 'published'
});
```

### 2. إنشاء وظيفة جديدة

```javascript
async function createJob(jobData) {
  const response = await fetch('/api/jobs.php?lang=ar', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(jobData)
  });

  const data = await response.json();
  
  if (data.success) {
    console.log('Job created with ID:', data.data.id);
  } else {
    console.error('Error:', data.message);
  }
}

// استخدام
createJob({
  entity_id: 123,
  job_title: 'مطور Frontend',
  description: 'نبحث عن مطور Frontend...',
  job_type: 'full_time',
  experience_level: 'mid',
  country_id: 1,
  salary_min: 7000,
  salary_max: 10000
});
```

### 3. تحديث وظيفة

```javascript
async function updateJob(jobId, updates) {
  const response = await fetch('/api/jobs.php?lang=ar', {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      id: jobId,
      ...updates
    })
  });

  const data = await response.json();
  return data;
}

// استخدام
updateJob(5, {
  job_title: 'مطور Frontend Senior',
  salary_min: 8000,
  status: 'published'
});
```

### 4. نشر وظيفة

```javascript
async function publishJob(jobId) {
  const response = await fetch(`/api/jobs.php?id=${jobId}&action=publish`, {
    method: 'PATCH'
  });

  const data = await response.json();
  return data;
}
```

### 5. البحث في الوظائف

```javascript
async function searchJobs(keyword, page = 1) {
  const params = new URLSearchParams({
    search: keyword,
    page: page,
    limit: 20,
    status: 'published',
    lang: 'ar'
  });

  const response = await fetch(`/api/jobs.php?${params}`);
  const data = await response.json();
  
  return data.data;
}

// استخدام
searchJobs('مطور').then(result => {
  console.log('Found:', result.meta.total, 'jobs');
  console.log('Jobs:', result.items);
});
```

### 6. إدارة الترجمات

```javascript
async function addTranslation(jobId, langCode, translationData) {
  const response = await fetch(`/api/jobs.php?job_id=${jobId}&translation=1`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      language_code: langCode,
      ...translationData
    })
  });

  return await response.json();
}

// استخدام
addTranslation(5, 'en', {
  job_title: 'Full Stack Developer',
  description: 'We are looking for...',
  requirements: '- 3+ years experience',
  responsibilities: '- Develop applications',
  benefits: '- Competitive salary'
});
```

---

## 🔍 الفرز المتقدم

الأعمدة المتاحة للفرز:
- `id`
- `entity_id`
- `job_title` (من الترجمة)
- `job_type`
- `experience_level`
- `salary_min`
- `salary_max`
- `views_count`
- `applications_count`
- `is_featured`
- `is_urgent`
- `created_at`
- `published_at`
- `application_deadline`

**أمثلة:**

```http
# الأحدث أولاً
GET /api/jobs.php?order_by=created_at&order_dir=DESC

# الأكثر مشاهدة
GET /api/jobs.php?order_by=views_count&order_dir=DESC

# الأعلى راتباً
GET /api/jobs.php?order_by=salary_max&order_dir=DESC

# حسب موعد انتهاء التقديم
GET /api/jobs.php?order_by=application_deadline&order_dir=ASC
```

---

## ⚠️ رموز الأخطاء

| الكود | الوصف |
|------|-------|
| 200 | نجاح |
| 201 | تم الإنشاء بنجاح |
| 400 | طلب غير صحيح |
| 401 | غير مصرح |
| 404 | غير موجود |
| 422 | خطأ في التحقق من البيانات |
| 500 | خطأ في الخادم |

**مثال على خطأ:**
```json
{
  "success": false,
  "message": "Field 'job_title' is required.",
  "error_code": 422
}
```

---

## 📝 ملاحظات مهمة

1. **اللغة الافتراضية:** العربية (`ar`)
2. **الترميز:** UTF-8
3. **التواريخ:** بصيغة `Y-m-d H:i:s`
4. **الحد الأقصى للنتائج:** 1000 لكل صفحة
5. **المشاهدات:** يتم زيادتها تلقائياً عند عرض الوظيفة (إلا في وضع المعاينة)
6. **Slug:** يتم توليده تلقائياً من `job_title` إذا لم يُحدد
7. **الترجمات:** يتم حفظها تلقائياً عند الإنشاء/التحديث

---

## 🎯 حالات الاستخدام الشائعة

### 1. صفحة الوظائف العامة
```javascript
// عرض الوظائف المنشورة فقط مع الترتيب حسب الأحدث
getJobs(1, { 
  status: 'published',
  order_by: 'published_at',
  order_dir: 'DESC'
});
```

### 2. لوحة تحكم الشركة
```javascript
// عرض جميع وظائف الشركة
getJobs(1, {
  entity_id: 123
});
```

### 3. صفحة البحث
```javascript
// بحث مع فلاتر متقدمة
searchJobs('مطور', {
  job_type: 'full_time',
  experience_level: 'mid',
  country_id: 1,
  salary_min: 5000
});
```

### 4. الوظائف المميزة في الصفحة الرئيسية
```javascript
fetch('/api/jobs.php?featured=1&featured_limit=5&status=published&lang=ar')
  .then(res => res.json())
  .then(data => {
    displayFeaturedJobs(data.data.items);
  });
```

---

## 🔐 الأمان

- جميع المدخلات يتم التحقق منها عبر `JobsValidator`
- استخدام Prepared Statements لمنع SQL Injection
- التحقق من صحة التواريخ والقيم الرقمية
- فلترة الحقول المسموح بها فقط

---

## 📞 الدعم

للمساعدة أو الإبلاغ عن مشاكل، يرجى مراجعة:
- ملفات الـ Logs في حالة الأخطاء
- `JobsValidator` للحقول المطلوبة والقيم المسموحة
- التأكد من صحة البيانات المرسلة

---

**آخر تحديث:** فبراير 2026
