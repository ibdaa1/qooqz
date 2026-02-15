# 📧 Job Alerts API - دليل الاستخدام الشامل

نظام إدارة تنبيهات الوظائف مع دعم الإشعارات الفورية واليومية والأسبوعية.

---

## 📁 هيكل الملفات

```
job-alerts/
├── repositories/
│   └── PdoJobAlertsRepository.php               # إدارة التنبيهات
├── validators/
│   └── JobAlertsValidator.php                   # التحقق من البيانات
├── services/
│   └── JobAlertsService.php                     # منطق الأعمال
├── controllers/
│   └── JobAlertsController.php                  # التحكم بالطلبات
└── api/routes/job_alerts.php                    # نقطة الدخول الرئيسية
```

---

## 🌟 الميزات الرئيسية

- ✅ **تنبيهات مخصصة** بناءً على معايير البحث
- ✅ **ثلاثة أنواع تردد**: فوري، يومي، أسبوعي
- ✅ **فلترة متقدمة** (نوع الوظيفة، المستوى، الموقع، الراتب)
- ✅ **البحث بالكلمات المفتاحية**
- ✅ **إحصائيات شاملة** للمستخدم
- ✅ **تفعيل/تعطيل** سريع
- ✅ **إدارة جماعية** (Batch Operations)
- ✅ **حد أقصى للتنبيهات** لكل مستخدم
- ✅ **دعم الجدولة** (Cron Jobs) للإرسال التلقائي

---

## 📊 بنية البيانات

### جدول job_alerts:

| الحقل | النوع | مطلوب | الوصف |
|------|------|-------|-------|
| `id` | bigint | تلقائي | معرف التنبيه |
| `user_id` | integer | ✅ | معرف المستخدم |
| `alert_name` | string(255) | ✅ | اسم التنبيه |
| `keywords` | string(500) | ❌ | كلمات مفتاحية |
| `job_type` | string(100) | ❌ | نوع الوظيفة |
| `experience_level` | string(100) | ❌ | مستوى الخبرة |
| `country_id` | integer | ❌ | معرف الدولة |
| `city_id` | integer | ❌ | معرف المدينة |
| `salary_min` | decimal | ❌ | الحد الأدنى للراتب |
| `is_active` | boolean | افتراضي: 1 | نشط/غير نشط |
| `frequency` | enum | افتراضي: daily | instant/daily/weekly |
| `last_sent_at` | datetime | تلقائي | آخر مرة تم الإرسال |
| `created_at` | datetime | تلقائي | تاريخ الإنشاء |
| `updated_at` | datetime | تلقائي | تاريخ التحديث |

### قيم صحيحة:

- **frequency:** instant, daily, weekly
- **job_type:** full-time, part-time, contract, freelance, internship, remote
- **experience_level:** entry, junior, mid, senior, lead, executive

---

## 📡 API Endpoints

### 1. قائمة التنبيهات (List Alerts)

**الطلب:**
```http
GET /api/job_alerts?user_id=1
```

**المعاملات:**

| المعامل | النوع | الافتراضي | الوصف |
|---------|------|-----------|-------|
| `user_id` | integer | - | **مطلوب** - معرف المستخدم |
| `page` | integer | 1 | رقم الصفحة |
| `limit` | integer | 25 | عدد النتائج (max: 1000) |
| `order_by` | string | 'created_at' | الترتيب حسب |
| `order_dir` | string | 'DESC' | اتجاه الترتيب |

**الفلاتر:**

| الفلتر | النوع | الوصف |
|--------|------|-------|
| `id` | integer | معرف التنبيه |
| `job_type` | string | نوع الوظيفة |
| `experience_level` | string | مستوى الخبرة |
| `country_id` | integer | معرف الدولة |
| `city_id` | integer | معرف المدينة |
| `is_active` | integer | نشط (0/1) |
| `frequency` | string | instant/daily/weekly |
| `search` | string | البحث في الاسم والكلمات المفتاحية |
| `salary_min` | decimal | الحد الأدنى للراتب |
| `salary_max` | decimal | الحد الأقصى للراتب |

**مثال - تنبيهات نشطة فقط:**
```http
GET /api/job_alerts?user_id=1&is_active=1
```

**مثال - تنبيهات يومية:**
```http
GET /api/job_alerts?user_id=1&frequency=daily
```

**مثال - البحث:**
```http
GET /api/job_alerts?user_id=1&search=developer
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "user_id": 1,
        "alert_name": "وظائف تطوير البرمجيات",
        "keywords": "php, laravel, developer",
        "job_type": "full-time",
        "experience_level": "mid",
        "country_id": 1,
        "city_id": 5,
        "salary_min": 8000.00,
        "is_active": 1,
        "frequency": "daily",
        "last_sent_at": "2026-02-14 10:00:00",
        "created_at": "2026-02-01 10:00:00",
        "updated_at": "2026-02-14 10:00:00",
        "user_name": "أحمد محمد",
        "user_email": "ahmad@example.com",
        "country_name": "السعودية",
        "city_name": "الرياض"
      }
    ],
    "meta": {
      "total": 1,
      "page": 1,
      "per_page": 25,
      "total_pages": 1,
      "from": 1,
      "to": 1
    }
  }
}
```

---

### 2. تنبيه واحد (Get Single Alert)

**الطلب:**
```http
GET /api/job_alerts?user_id=1&id=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "alert_name": "وظائف تطوير البرمجيات",
    "keywords": "php, laravel, developer",
    "job_type": "full-time",
    "experience_level": "mid",
    "country_id": 1,
    "city_id": 5,
    "salary_min": 8000.00,
    "is_active": 1,
    "frequency": "daily",
    "last_sent_at": "2026-02-14 10:00:00",
    "created_at": "2026-02-01 10:00:00",
    "updated_at": "2026-02-14 10:00:00"
  }
}
```

---

### 3. إنشاء تنبيه جديد (Create Alert)

**الطلب:**
```http
POST /api/job_alerts?user_id=1
Content-Type: application/json
```

**البيانات:**
```json
{
  "alert_name": "وظائف تطوير البرمجيات في الرياض",
  "keywords": "php, laravel, mysql, api",
  "job_type": "full-time",
  "experience_level": "mid",
  "country_id": 1,
  "city_id": 5,
  "salary_min": 8000,
  "is_active": 1,
  "frequency": "daily"
}
```

**الحقول الاختيارية:**
- `keywords`: كلمات مفتاحية للبحث
- `job_type`: full-time, part-time, contract, freelance, internship, remote
- `experience_level`: entry, junior, mid, senior, lead, executive
- `country_id`, `city_id`: للتحديد الجغرافي
- `salary_min`: الحد الأدنى للراتب
- `is_active`: افتراضياً `1`
- `frequency`: افتراضياً `daily`

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 10
  },
  "message": "Alert created"
}
```

---

### 4. تحديث تنبيه (Update Alert)

**الطلب:**
```http
PUT /api/job_alerts?user_id=1
Content-Type: application/json
```

**البيانات:**
```json
{
  "id": 10,
  "alert_name": "وظائف تطوير البرمجيات - محدث",
  "keywords": "php, laravel, vue.js, api",
  "salary_min": 10000,
  "frequency": "instant"
}
```

---

### 5. حذف تنبيه (Delete Alert)

**الطلب:**
```http
DELETE /api/job_alerts?user_id=1
Content-Type: application/json

{
  "id": 10
}
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "deleted": true
  },
  "message": "Alert deleted"
}
```

---

### 6. تفعيل/تعطيل تنبيه (Toggle Active Status)

**الطلب:**
```http
PATCH /api/job_alerts?user_id=1&id=1&toggle_active=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "toggled": true
  },
  "message": "Status toggled"
}
```

---

### 7. إحصائيات المستخدم (User Statistics)

**الطلب:**
```http
GET /api/job_alerts?user_id=1&statistics=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "total_alerts": 10,
    "active_alerts": 7,
    "inactive_alerts": 3,
    "instant_alerts": 2,
    "daily_alerts": 5,
    "weekly_alerts": 3,
    "latest_alert_date": "2026-02-14 10:00:00"
  }
}
```

---

### 8. التحقق من إمكانية إنشاء تنبيه جديد (Check Quota)

**الطلب:**
```http
GET /api/job_alerts?user_id=1&can_create=1&max_alerts=10
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "can_create": true,
    "active_alerts": 7,
    "max_alerts": 10
  }
}
```

---

### 9. الحصول على التنبيهات المستحقة للإرسال (Due Alerts)

**للاستخدام بواسطة Cron Jobs**

**الطلب:**
```http
GET /api/job_alerts?due_alerts=1&frequency=daily
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "alerts": [
      {
        "id": 1,
        "user_id": 5,
        "user_name": "أحمد محمد",
        "user_email": "ahmad@example.com",
        "alert_name": "وظائف تطوير البرمجيات",
        "keywords": "php, laravel",
        "last_sent_at": "2026-02-13 10:00:00"
      }
    ]
  }
}
```

---

### 10. تحديث وقت الإرسال الأخير (Update Last Sent)

**للاستخدام بعد إرسال التنبيه**

**الطلب:**
```http
PATCH /api/job_alerts?update_last_sent=1&alert_id=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "updated": true
  },
  "message": "Last sent updated"
}
```

---

### 11. تحديث جماعي للحالة (Batch Update Status)

**الطلب:**
```http
PATCH /api/job_alerts?user_id=1&batch_update=1
Content-Type: application/json

{
  "alert_ids": [1, 2, 3, 4, 5],
  "is_active": 0
}
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "updated": 5,
    "total": 5
  },
  "message": "Batch update completed"
}
```

---

### 12. الحصول على القيم الصالحة (Valid Values)

**الترددات:**
```http
GET /api/job_alerts?valid_frequencies=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "frequencies": ["instant", "daily", "weekly"]
  }
}
```

**أنواع الوظائف:**
```http
GET /api/job_alerts?valid_job_types=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "job_types": ["full-time", "part-time", "contract", "freelance", "internship", "remote"]
  }
}
```

**مستويات الخبرة:**
```http
GET /api/job_alerts?valid_experience_levels=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "experience_levels": ["entry", "junior", "mid", "senior", "lead", "executive"]
  }
}
```

---

## 💡 أمثلة الاستخدام بـ JavaScript

### 1. الحصول على قائمة التنبيهات

```javascript
async function getJobAlerts(userId, filters = {}) {
  const params = new URLSearchParams({
    user_id: userId,
    ...filters
  });

  const response = await fetch(`/api/job_alerts?${params}`);
  const data = await response.json();
  
  if (data.success) {
    return data.data;
  }
  throw new Error(data.message);
}

// استخدام
getJobAlerts(1, { is_active: 1, frequency: 'daily' })
  .then(result => {
    console.log('Alerts:', result.items);
    console.log('Total:', result.meta.total);
  });
```

### 2. إنشاء تنبيه جديد

```javascript
async function createJobAlert(userId, alertData) {
  const response = await fetch(`/api/job_alerts?user_id=${userId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(alertData)
  });

  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message);
  }
  
  return data.data.id;
}

// استخدام
createJobAlert(1, {
  alert_name: 'وظائف تطوير الويب',
  keywords: 'php, javascript, laravel',
  job_type: 'full-time',
  experience_level: 'mid',
  country_id: 1,
  city_id: 5,
  salary_min: 8000,
  frequency: 'daily'
}).then(newId => {
  console.log('Created alert ID:', newId);
});
```

### 3. تفعيل/تعطيل تنبيه

```javascript
async function toggleAlert(userId, alertId) {
  const response = await fetch(
    `/api/job_alerts?user_id=${userId}&id=${alertId}&toggle_active=1`,
    { method: 'PATCH' }
  );

  return await response.json();
}

// استخدام
toggleAlert(1, 1).then(result => {
  console.log('Toggled:', result.data.toggled);
});
```

### 4. حذف تنبيه

```javascript
async function deleteJobAlert(userId, alertId) {
  const response = await fetch(`/api/job_alerts?user_id=${userId}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ id: alertId })
  });

  return await response.json();
}

// استخدام
deleteJobAlert(1, 1).then(result => {
  console.log('Deleted:', result.data.deleted);
});
```

### 5. الحصول على الإحصائيات

```javascript
async function getAlertStatistics(userId) {
  const response = await fetch(
    `/api/job_alerts?user_id=${userId}&statistics=1`
  );
  const data = await response.json();
  
  return data.data;
}

// استخدام
getAlertStatistics(1).then(stats => {
  console.log('Total alerts:', stats.total_alerts);
  console.log('Active alerts:', stats.active_alerts);
  console.log('Daily alerts:', stats.daily_alerts);
});
```

### 6. تحديث جماعي

```javascript
async function batchUpdateAlerts(userId, alertIds, isActive) {
  const response = await fetch(
    `/api/job_alerts?user_id=${userId}&batch_update=1`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        alert_ids: alertIds,
        is_active: isActive
      })
    }
  );

  return await response.json();
}

// استخدام - تعطيل عدة تنبيهات
batchUpdateAlerts(1, [1, 2, 3, 4], false).then(result => {
  console.log(`Updated ${result.data.updated} of ${result.data.total} alerts`);
});
```

---

## 🎨 مثال عملي - واجهة مستخدم

### HTML Structure
```html
<div id="alerts-manager">
  <!-- Toolbar -->
  <div class="toolbar">
    <button onclick="loadAlerts()">تحديث القائمة</button>
    <button onclick="showCreateForm()">إنشاء تنبيه جديد</button>
    <button onclick="showStatistics()">الإحصائيات</button>
  </div>

  <!-- Filters -->
  <div class="filters">
    <select id="filter-frequency">
      <option value="">كل الترددات</option>
      <option value="instant">فوري</option>
      <option value="daily">يومي</option>
      <option value="weekly">أسبوعي</option>
    </select>
    
    <select id="filter-status">
      <option value="">الكل</option>
      <option value="1">نشط</option>
      <option value="0">غير نشط</option>
    </select>
    
    <input type="text" id="search" placeholder="بحث...">
    <button onclick="applyFilters()">بحث</button>
  </div>

  <!-- Alerts List -->
  <div id="alerts-list" class="alerts-container"></div>

  <!-- Create/Edit Form -->
  <div id="alert-form" class="modal">
    <h3>إنشاء تنبيه جديد</h3>
    <input type="text" id="alert-name" placeholder="اسم التنبيه" required>
    <input type="text" id="keywords" placeholder="كلمات مفتاحية">
    
    <select id="job-type">
      <option value="">نوع الوظيفة</option>
      <option value="full-time">دوام كامل</option>
      <option value="part-time">دوام جزئي</option>
      <option value="remote">عن بعد</option>
    </select>
    
    <select id="experience-level">
      <option value="">مستوى الخبرة</option>
      <option value="entry">مبتدئ</option>
      <option value="junior">Junior</option>
      <option value="mid">متوسط</option>
      <option value="senior">Senior</option>
    </select>
    
    <select id="frequency">
      <option value="instant">فوري</option>
      <option value="daily">يومي</option>
      <option value="weekly">أسبوعي</option>
    </select>
    
    <input type="number" id="salary-min" placeholder="الحد الأدنى للراتب">
    
    <button onclick="saveAlert()">حفظ</button>
    <button onclick="closeForm()">إلغاء</button>
  </div>
</div>
```

### JavaScript Implementation
```javascript
class JobAlertsManager {
  constructor(userId) {
    this.userId = userId;
    this.currentFilters = {};
  }

  async loadAlerts() {
    try {
      const params = new URLSearchParams({
        user_id: this.userId,
        ...this.currentFilters
      });

      const response = await fetch(`/api/job_alerts?${params}`);
      const data = await response.json();
      
      if (data.success) {
        this.renderAlerts(data.data.items);
        this.renderPagination(data.data.meta);
      }
    } catch (error) {
      console.error('Error loading alerts:', error);
    }
  }

  renderAlerts(alerts) {
    const container = document.getElementById('alerts-list');
    container.innerHTML = '';

    alerts.forEach(alert => {
      const item = document.createElement('div');
      item.className = 'alert-item';
      item.innerHTML = `
        <div class="alert-header">
          <h3>${alert.alert_name}</h3>
          <span class="badge ${alert.is_active ? 'active' : 'inactive'}">
            ${alert.is_active ? 'نشط' : 'غير نشط'}
          </span>
          <span class="badge frequency">${alert.frequency}</span>
        </div>
        <div class="alert-body">
          <p><strong>الكلمات المفتاحية:</strong> ${alert.keywords || '-'}</p>
          <p><strong>نوع الوظيفة:</strong> ${alert.job_type || '-'}</p>
          <p><strong>المستوى:</strong> ${alert.experience_level || '-'}</p>
          <p><strong>الموقع:</strong> ${alert.city_name ? alert.city_name + ', ' + alert.country_name : '-'}</p>
          <p><strong>الراتب:</strong> ${alert.salary_min ? alert.salary_min + '+' : '-'}</p>
          <p><strong>آخر إرسال:</strong> ${alert.last_sent_at || 'لم يرسل بعد'}</p>
        </div>
        <div class="alert-actions">
          <button onclick="manager.toggleActive(${alert.id})">
            ${alert.is_active ? 'تعطيل' : 'تفعيل'}
          </button>
          <button onclick="manager.edit(${alert.id})">تعديل</button>
          <button onclick="manager.delete(${alert.id})" class="danger">حذف</button>
        </div>
      `;
      
      container.appendChild(item);
    });
  }

  async toggleActive(alertId) {
    const response = await fetch(
      `/api/job_alerts?user_id=${this.userId}&id=${alertId}&toggle_active=1`,
      { method: 'PATCH' }
    );
    
    const data = await response.json();
    
    if (data.success) {
      this.loadAlerts(); // Reload
    }
  }

  async delete(alertId) {
    if (!confirm('هل أنت متأكد من حذف هذا التنبيه؟')) return;

    const response = await fetch(`/api/job_alerts?user_id=${this.userId}`, {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id: alertId })
    });

    const data = await response.json();
    
    if (data.success) {
      this.loadAlerts(); // Reload
    }
  }

  async save(alertData) {
    const method = alertData.id ? 'PUT' : 'POST';
    const response = await fetch(
      `/api/job_alerts?user_id=${this.userId}`,
      {
        method: method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(alertData)
      }
    );

    const data = await response.json();
    
    if (data.success) {
      document.getElementById('alert-form').style.display = 'none';
      this.loadAlerts(); // Reload
    } else {
      alert(data.message);
    }
  }

  async showStatistics() {
    const response = await fetch(
      `/api/job_alerts?user_id=${this.userId}&statistics=1`
    );
    const data = await response.json();
    
    if (data.success) {
      const stats = data.data;
      alert(`
        إجمالي التنبيهات: ${stats.total_alerts}
        نشط: ${stats.active_alerts}
        غير نشط: ${stats.inactive_alerts}
        فوري: ${stats.instant_alerts}
        يومي: ${stats.daily_alerts}
        أسبوعي: ${stats.weekly_alerts}
      `);
    }
  }

  applyFilters() {
    const frequency = document.getElementById('filter-frequency').value;
    const isActive = document.getElementById('filter-status').value;
    const search = document.getElementById('search').value;

    this.currentFilters = {};
    if (frequency) this.currentFilters.frequency = frequency;
    if (isActive !== '') this.currentFilters.is_active = isActive;
    if (search) this.currentFilters.search = search;

    this.loadAlerts();
  }
}

// Initialize
const manager = new JobAlertsManager(1); // User ID
manager.loadAlerts();
```

---

## 🔄 Cron Jobs للإرسال التلقائي

### 1. إرسال التنبيهات الفورية
```bash
# كل دقيقة
* * * * * php /path/to/send_instant_alerts.php
```

```php
<?php
// send_instant_alerts.php
require_once 'config.php';

$response = file_get_contents('/api/job_alerts?due_alerts=1&frequency=instant');
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data']['alerts'] as $alert) {
        // إرسال بريد إلكتروني أو إشعار
        sendAlertNotification($alert);
        
        // تحديث last_sent_at
        file_get_contents("/api/job_alerts?update_last_sent=1&alert_id={$alert['id']}");
    }
}
```

### 2. إرسال التنبيهات اليومية
```bash
# كل يوم في الساعة 8 صباحاً
0 8 * * * php /path/to/send_daily_alerts.php
```

### 3. إرسال التنبيهات الأسبوعية
```bash
# كل اثنين في الساعة 9 صباحاً
0 9 * * 1 php /path/to/send_weekly_alerts.php
```

---

## ⚠️ ملاحظات مهمة

1. **الحد الأقصى:** يمكن تعيين حد أقصى للتنبيهات لكل مستخدم (افتراضياً 50)
2. **الترددات:**
   - **instant**: يُرسل فوراً عند نشر وظيفة مطابقة
   - **daily**: يُرسل مرة يومياً في وقت محدد
   - **weekly**: يُرسل مرة أسبوعياً
3. **الفلاتر:** جميع الفلاتر اختيارية ماعدا `user_id`
4. **الأمان:** كل مستخدم يرى تنبيهاته فقط
5. **last_sent_at:** يتم تحديثه تلقائياً بعد إرسال التنبيه
6. **الجداول الاختيارية:** countries و cities غير مطلوبة، لكنها تضيف معلومات إضافية

---

## 🎯 Best Practices

1. **استخدم الكلمات المفتاحية بحكمة:** فصل بينها بفواصل
2. **حدد الموقع:** لتقليل التنبيهات غير المناسبة
3. **استخدم التردد المناسب:** instant للمهم، daily للعادي
4. **راجع التنبيهات دورياً:** احذف أو عطّل القديمة
5. **استخدم البحث:** للعثور على تنبيهات محددة بسرعة
6. **راقب الإحصائيات:** لفهم أداء التنبيهات

---

**آخر تحديث:** فبراير 2026

**الحالة:** مكتمل 100% ✅

**الاختبار:** جميع الـ Endpoints تعمل بشكل صحيح:
- ✅ قائمة التنبيهات
- ✅ تنبيه واحد
- ✅ تفعيل/تعطيل
- ✅ الإحصائيات
- ✅ التحقق من الحصة
- ✅ التنبيهات المستحقة
- ✅ التحديث الجماعي
- ✅ القيم الصالحة