# 📂 Job Categories API - دليل الاستخدام الشامل

نظام إدارة فئات الوظائف الهرمي (Hierarchical) مع دعم متعدد اللغات.

---

## 📁 هيكل الملفات

```
job-categories/
├── repositories/
│   ├── PdoJobCategoriesRepository.php           # إدارة الفئات
│   └── PdoJobCategoryTranslationsRepository.php # إدارة الترجمات
├── validators/
│   └── JobCategoriesValidator.php               # التحقق من البيانات
├── services/
│   └── JobCategoriesService.php                 # منطق الأعمال
├── controllers/
│   └── JobCategoriesController.php              # التحكم بالطلبات
├── api/routes/
│   └── job-categories.php                       # نقطة الدخول الرئيسية
└── admin/                                       # واجهة الإدارة
    ├── fragments/
    │   └── job_categories.php                   # صفحة الإدارة الرئيسية
    ├── assets/js/pages/
    │   └── job_categories.js                    # منطق واجهة المستخدم
    └── assets/css/pages/
        └── job_categories.css                   # تنسيقات الواجهة
```

---

## 🌟 الميزات الرئيسية

### API Features
- ✅ **هيكل شجري** (Parent-Child Hierarchy)
- ✅ **دعم متعدد اللغات** (Multilingual)
- ✅ **الترتيب المخصص** (Custom Sort Order)
- ✅ **إدارة الترجمات** الكاملة
- ✅ **البحث والفلترة** المتقدمة
- ✅ **نقل الفئات** بين المستويات
- ✅ **إعادة الترتيب** Batch Reordering

### Admin UI Features
- ✅ **واجهة إدارة متقدمة** (Modern Admin Interface)
- ✅ **دعم RTL/LTR** تلقائي حسب اللغة
- ✅ **إدارة الترجمات المرئية** (Visual Translation Management)
- ✅ **تكامل مع Media Studio** (image_types.id=11)
- ✅ **اختيار الفئة الأب** (Parent Category Selection)
- ✅ **فلترة وبحث متقدم** (Advanced Filtering & Search)
- ✅ **صلاحيات متعددة المستويات** (Permission Checks)
- ✅ **تصميم متجاوب** (Responsive Design)

---

## 📊 البنية الهرمية

```
الفئة الرئيسية (parent_id = NULL)
├── فئة فرعية 1 (parent_id = 1)
│   ├── فئة فرعية 1.1
│   └── فئة فرعية 1.2
└── فئة فرعية 2 (parent_id = 1)
    └── فئة فرعية 2.1
```

---

## 🎨 واجهة الإدارة (Admin UI)

### الوصول إلى الواجهة

```
/admin/fragments/job_categories.php
```

### الميزات الرئيسية

1. **إدارة الفئات:**
   - إنشاء فئة جديدة
   - تعديل فئة موجودة
   - حذف فئة
   - اختيار الفئة الأب (هرمية)

2. **إدارة الترجمات:**
   - إضافة ترجمات لغات متعددة
   - تعديل الترجمات
   - حذف ترجمات
   - عرض اللغات المتاحة

3. **إدارة الوسائط:**
   - تحميل صورة الفئة
   - تحميل أيقونة الفئة
   - تكامل مع Media Studio
   - نوع الصورة المخصص (image_types.id=11)

4. **الفلترة والبحث:**
   - البحث بالاسم أو Slug
   - الفلترة حسب الفئة الأب
   - الفلترة حسب الحالة (نشط/غير نشط)

5. **الصلاحيات:**
   - صلاحيات المشاهدة (View All, View Own, View Tenant)
   - صلاحيات الإنشاء (Create)
   - صلاحيات التعديل (Edit All, Edit Own)
   - صلاحيات الحذف (Delete All, Delete Own)

### لقطات شاشة للواجهة

#### 1. قائمة الفئات
- جدول يعرض جميع الفئات
- أعمدة: ID, الصورة, الاسم, Slug, الفئة الأب, الترتيب, الحالة, الإجراءات
- فلترة وبحث متقدم
- ترقيم الصفحات

#### 2. نموذج الإضافة/التعديل
- **تبويب المعلومات الأساسية:**
  - الفئة الأب (اختياري)
  - Slug (يتم توليده تلقائياً)
  - ترتيب العرض
  - الحالة (نشط/غير نشط)

- **تبويب الترجمات:**
  - اختيار اللغة
  - إضافة ترجمة جديدة
  - حقول: الاسم، الوصف
  - حذف ترجمة

- **تبويب الوسائط:**
  - صورة الفئة
  - أيقونة الفئة
  - نوع الصورة (Job Category - ID: 11)

### استخدام الواجهة

```javascript
// مثال على الوصول إلى الواجهة من القائمة الجانبية
<a href="/admin/fragments/job_categories.php">
    <i class="fas fa-briefcase"></i>
    <span>Job Categories</span>
</a>
```

### التكامل مع AdminFramework

الواجهة تستخدم `AdminFramework (AF)` للعمليات التالية:
- طلبات AJAX (`AF.ajax()`)
- الإشعارات (`AF.notify()`)
- إدارة الحالة
- التحقق من النماذج

### دعم RTL/LTR

الواجهة تدعم تلقائياً:
- RTL للغات: العربية (ar), العبرية (he), الفارسية (fa), الأوردو (ur)
- LTR لبقية اللغات
- تبديل الاتجاه تلقائياً حسب اللغة المختارة

### Database Schema

```sql
-- جدول الفئات الرئيسي
CREATE TABLE job_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT NULL,
    slug VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (parent_id) REFERENCES job_categories(id),
    UNIQUE KEY unique_tenant_slug (tenant_id, slug)
);

-- جدول الترجمات
CREATE TABLE job_category_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES job_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_category_language (category_id, language_code)
);
```

---

## 📡 API Endpoints

### 1. قائمة الفئات (List Categories)

**الطلب:**
```http
GET /api/job-categories.php?tenant_id=1&lang=ar
```

**المعاملات:**

| المعامل | النوع | الافتراضي | الوصف |
|---------|------|-----------|-------|
| `tenant_id` | integer | - | **مطلوب** - معرف المستأجر |
| `page` | integer | 1 | رقم الصفحة |
| `limit` | integer | 100 | عدد النتائج (max: 1000) |
| `lang` | string | 'ar' | كود اللغة |
| `order_by` | string | 'sort_order' | الترتيب حسب |
| `order_dir` | string | 'ASC' | اتجاه الترتيب |

**الفلاتر:**

| الفلتر | النوع | الوصف |
|--------|------|-------|
| `parent_id` | integer/null | معرف الفئة الأم |
| `is_active` | integer | نشط (0/1) |
| `search` | string | البحث في الاسم |

**مثال - الفئات الرئيسية فقط:**
```http
GET /api/job-categories.php?tenant_id=1&parent_id=null&lang=ar
```

**مثال - فئات فرعية:**
```http
GET /api/job-categories.php?tenant_id=1&parent_id=5&lang=ar
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "tenant_id": 1,
        "parent_id": null,
        "slug": "it-technology",
        "sort_order": 1,
        "is_active": 1,
        "name": "تقنية المعلومات",
        "description": "وظائف في مجال التقنية والبرمجة",
        "children_count": 5,
        "created_at": "2026-02-01 10:00:00"
      },
      {
        "id": 2,
        "tenant_id": 1,
        "parent_id": null,
        "slug": "marketing",
        "sort_order": 2,
        "is_active": 1,
        "name": "التسويق",
        "description": "وظائف التسويق والمبيعات",
        "children_count": 3,
        "created_at": "2026-02-01 10:05:00"
      }
    ],
    "meta": {
      "total": 2,
      "page": 1,
      "per_page": 100,
      "total_pages": 1
    }
  }
}
```

---

### 2. الشجرة الكاملة (Category Tree)

**الحصول على الشجرة الكاملة:**
```http
GET /api/job-categories.php?tenant_id=1&tree=1&lang=ar
```

**الحصول على شجرة فرعية:**
```http
GET /api/job-categories.php?tenant_id=1&tree=1&parent_id=5&lang=ar
```

**الاستجابة:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "تقنية المعلومات",
      "slug": "it-technology",
      "sort_order": 1,
      "children": [
        {
          "id": 3,
          "name": "تطوير البرمجيات",
          "slug": "software-development",
          "sort_order": 1,
          "children": [
            {
              "id": 5,
              "name": "Frontend Development",
              "slug": "frontend-dev",
              "sort_order": 1,
              "children": []
            },
            {
              "id": 6,
              "name": "Backend Development",
              "slug": "backend-dev",
              "sort_order": 2,
              "children": []
            }
          ]
        },
        {
          "id": 4,
          "name": "أمن المعلومات",
          "slug": "information-security",
          "sort_order": 2,
          "children": []
        }
      ]
    }
  ]
}
```

---

### 3. الفئات الرئيسية (Root Categories)

```http
GET /api/job-categories.php?tenant_id=1&root=1&lang=ar
```

---

### 4. الفئات الفرعية (Children)

```http
GET /api/job-categories.php?tenant_id=1&parent_id=5&children=1&lang=ar
```

---

### 5. فئة واحدة (Get Single Category)

**بواسطة ID:**
```http
GET /api/job-categories.php?tenant_id=1&id=5&lang=ar
```

**بواسطة Slug:**
```http
GET /api/job-categories.php?tenant_id=1&slug=it-technology&lang=ar
```

**مع الترجمات:**
```http
GET /api/job-categories.php?tenant_id=1&id=5&with_translations=1&lang=ar
```

**الاستجابة مع الترجمات:**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "tenant_id": 1,
    "parent_id": 1,
    "slug": "software-development",
    "name": "تطوير البرمجيات",
    "description": "وظائف تطوير التطبيقات والبرمجيات",
    "children_count": 2,
    "translations": [
      {
        "id": 1,
        "category_id": 5,
        "language_code": "ar",
        "name": "تطوير البرمجيات",
        "description": "وظائف تطوير التطبيقات والبرمجيات",
        "language_name": "Arabic",
        "language_direction": "rtl"
      },
      {
        "id": 2,
        "category_id": 5,
        "language_code": "en",
        "name": "Software Development",
        "description": "Software and application development jobs",
        "language_name": "English",
        "language_direction": "ltr"
      }
    ],
    "available_languages": [
      {"language_code": "ar", "language_name": "Arabic"},
      {"language_code": "en", "language_name": "English"}
    ],
    "missing_languages": [
      {"code": "fr", "name": "French"}
    ]
  }
}
```

---

### 6. إنشاء فئة جديدة (Create Category)

**الطلب:**
```http
POST /api/job-categories.php?tenant_id=1&lang=ar
Content-Type: application/json
```

**البيانات:**
```json
{
  "name": "تطوير البرمجيات",
  "description": "وظائف تطوير التطبيقات والبرمجيات",
  "parent_id": 1,
  "slug": "software-development",
  "sort_order": 1,
  "is_active": 1
}
```

**الحقول الاختيارية:**
- `slug`: سيتم توليده تلقائياً من `name` إذا لم يُحدد
- `parent_id`: `null` للفئة الرئيسية
- `sort_order`: افتراضياً `0`
- `is_active`: افتراضياً `1`

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 10
  },
  "message": "Category created successfully"
}
```

---

### 7. تحديث فئة (Update Category)

**الطلب:**
```http
PUT /api/job-categories.php?tenant_id=1&lang=ar
Content-Type: application/json
```

**البيانات:**
```json
{
  "id": 10,
  "name": "تطوير التطبيقات",
  "description": "تحديث الوصف...",
  "sort_order": 5,
  "is_active": 1
}
```

---

### 8. حذف فئة (Delete Category)

**الطلب:**
```http
DELETE /api/job-categories.php?tenant_id=1
Content-Type: application/json

{
  "id": 10
}
```

**ملاحظة:** لا يمكن حذف فئة لها فئات فرعية.

---

### 9. إدارة الترجمات (Translations)

**الحصول على جميع الترجمات:**
```http
GET /api/job-categories.php?category_id=5&translations=1
```

**الحصول على اللغات المتاحة:**
```http
GET /api/job-categories.php?category_id=5&available_languages=1
```

**الحصول على اللغات المفقودة:**
```http
GET /api/job-categories.php?category_id=5&missing_languages=1
```

**حفظ/تحديث ترجمة:**
```http
PATCH /api/job-categories.php?category_id=5&translation=1
Content-Type: application/json

{
  "language_code": "en",
  "name": "Software Development",
  "description": "Software and application development jobs"
}
```

**حفظ ترجمات متعددة:**
```http
PATCH /api/job-categories.php?category_id=5&bulk_translations=1
Content-Type: application/json

{
  "translations": {
    "ar": {
      "name": "تطوير البرمجيات",
      "description": "وظائف تطوير التطبيقات"
    },
    "en": {
      "name": "Software Development",
      "description": "Software development jobs"
    },
    "fr": {
      "name": "Développement de logiciels",
      "description": "Emplois de développement de logiciels"
    }
  }
}
```

**حذف ترجمة:**
```http
PATCH /api/job-categories.php?category_id=5&delete_translation=1&lang_code=en
```

---

### 10. إدارة الترتيب والتنظيم

**تحديث الترتيب:**
```http
PATCH /api/job-categories.php?tenant_id=1&id=5
Content-Type: application/json

{
  "sort_order": 10
}
```

**نقل فئة لأب آخر:**
```http
PATCH /api/job-categories.php?tenant_id=1&id=5&action=move
Content-Type: application/json

{
  "parent_id": 3
}
```

**نقل لتصبح فئة رئيسية:**
```http
PATCH /api/job-categories.php?tenant_id=1&id=5&action=move
Content-Type: application/json

{
  "parent_id": null
}
```

**إعادة ترتيب متعددة (Batch Reordering):**
```http
PATCH /api/job-categories.php?tenant_id=1&action=reorder
Content-Type: application/json

{
  "order": [
    {"id": 1, "sort_order": 1},
    {"id": 2, "sort_order": 2},
    {"id": 3, "sort_order": 3},
    {"id": 4, "sort_order": 4}
  ]
}
```

---

## 💡 أمثلة الاستخدام بـ JavaScript

### 1. الحصول على الشجرة الكاملة

```javascript
async function getCategoryTree(tenantId, lang = 'ar') {
  const response = await fetch(
    `/api/job-categories.php?tenant_id=${tenantId}&tree=1&lang=${lang}`
  );
  const data = await response.json();
  
  if (data.success) {
    return data.data;
  }
  throw new Error(data.message);
}

// استخدام
getCategoryTree(1, 'ar').then(tree => {
  console.log('Category Tree:', tree);
  displayTree(tree);
});
```

### 2. إنشاء فئة جديدة

```javascript
async function createCategory(tenantId, categoryData, lang = 'ar') {
  const response = await fetch(
    `/api/job-categories.php?tenant_id=${tenantId}&lang=${lang}`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(categoryData)
    }
  );

  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message);
  }
  
  return data.data.id;
}

// استخدام
createCategory(1, {
  name: 'تطوير البرمجيات',
  description: 'وظائف تطوير التطبيقات',
  parent_id: 1,
  sort_order: 1
}, 'ar').then(newId => {
  console.log('Created category ID:', newId);
});
```

### 3. عرض الشجرة بشكل تفاعلي

```javascript
function displayTree(categories, parentElement, level = 0) {
  categories.forEach(category => {
    const div = document.createElement('div');
    div.style.marginLeft = `${level * 20}px`;
    div.innerHTML = `
      <span class="category-name">${category.name}</span>
      <span class="children-count">(${category.children.length})</span>
    `;
    
    parentElement.appendChild(div);
    
    if (category.children && category.children.length > 0) {
      displayTree(category.children, parentElement, level + 1);
    }
  });
}

// استخدام
const container = document.getElementById('category-tree');
getCategoryTree(1, 'ar').then(tree => {
  displayTree(tree, container);
});
```

### 4. نقل فئة

```javascript
async function moveCategory(tenantId, categoryId, newParentId) {
  const response = await fetch(
    `/api/job-categories.php?tenant_id=${tenantId}&id=${categoryId}&action=move`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        parent_id: newParentId
      })
    }
  );

  return await response.json();
}

// استخدام - نقل الفئة 5 لتصبح تحت الفئة 3
moveCategory(1, 5, 3).then(result => {
  console.log('Category moved:', result.data.moved);
});
```

### 5. إعادة ترتيب الفئات (Drag & Drop)

```javascript
async function reorderCategories(tenantId, orderData) {
  const response = await fetch(
    `/api/job-categories.php?tenant_id=${tenantId}&action=reorder`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        order: orderData
      })
    }
  );

  return await response.json();
}

// استخدام - بعد Drag & Drop
const newOrder = [
  {id: 3, sort_order: 1},
  {id: 1, sort_order: 2},
  {id: 2, sort_order: 3},
  {id: 4, sort_order: 4}
];

reorderCategories(1, newOrder).then(result => {
  console.log('Reordered:', result.data.reordered);
});
```

### 6. إدارة الترجمات

```javascript
async function saveTranslation(categoryId, langCode, translationData) {
  const response = await fetch(
    `/api/job-categories.php?category_id=${categoryId}&translation=1`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        language_code: langCode,
        ...translationData
      })
    }
  );

  return await response.json();
}

// استخدام
saveTranslation(5, 'en', {
  name: 'Software Development',
  description: 'Software development jobs'
}).then(result => {
  console.log('Translation saved');
});
```

---

## 🎨 مثال عملي - واجهة مستخدم

### HTML Structure
```html
<div id="category-manager">
  <!-- Toolbar -->
  <div class="toolbar">
    <button onclick="loadTree()">تحديث الشجرة</button>
    <button onclick="addRootCategory()">إضافة فئة رئيسية</button>
  </div>

  <!-- Tree View -->
  <div id="category-tree" class="tree-container"></div>

  <!-- Edit Form -->
  <div id="edit-form" class="modal">
    <h3>تعديل الفئة</h3>
    <input type="text" id="category-name" placeholder="الاسم">
    <textarea id="category-description" placeholder="الوصف"></textarea>
    <select id="parent-category">
      <option value="">فئة رئيسية</option>
    </select>
    <input type="number" id="sort-order" placeholder="الترتيب">
    <button onclick="saveCategory()">حفظ</button>
  </div>
</div>
```

### JavaScript Implementation
```javascript
class CategoryManager {
  constructor(tenantId) {
    this.tenantId = tenantId;
    this.currentLang = 'ar';
  }

  async loadTree() {
    try {
      const response = await fetch(
        `/api/job-categories.php?tenant_id=${this.tenantId}&tree=1&lang=${this.currentLang}`
      );
      const data = await response.json();
      
      if (data.success) {
        this.renderTree(data.data);
      }
    } catch (error) {
      console.error('Error loading tree:', error);
    }
  }

  renderTree(categories, container = null, level = 0) {
    if (!container) {
      container = document.getElementById('category-tree');
      container.innerHTML = '';
    }

    categories.forEach(category => {
      const item = document.createElement('div');
      item.className = 'tree-item';
      item.style.marginLeft = `${level * 20}px`;
      item.innerHTML = `
        <span class="name">${category.name}</span>
        <span class="count">(${category.children.length})</span>
        <div class="actions">
          <button onclick="manager.edit(${category.id})">تعديل</button>
          <button onclick="manager.addChild(${category.id})">إضافة فرعية</button>
          <button onclick="manager.delete(${category.id})">حذف</button>
        </div>
      `;
      
      container.appendChild(item);
      
      if (category.children.length > 0) {
        this.renderTree(category.children, container, level + 1);
      }
    });
  }

  async edit(categoryId) {
    // Load category data and show edit form
    const response = await fetch(
      `/api/job-categories.php?tenant_id=${this.tenantId}&id=${categoryId}&lang=${this.currentLang}`
    );
    const data = await response.json();
    
    if (data.success) {
      document.getElementById('category-name').value = data.data.name;
      document.getElementById('category-description').value = data.data.description || '';
      // Show modal...
    }
  }

  async save(categoryData) {
    const method = categoryData.id ? 'PUT' : 'POST';
    const response = await fetch(
      `/api/job-categories.php?tenant_id=${this.tenantId}&lang=${this.currentLang}`,
      {
        method: method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(categoryData)
      }
    );

    const data = await response.json();
    
    if (data.success) {
      this.loadTree(); // Reload
    }
  }
}

// Initialize
const manager = new CategoryManager(1);
manager.loadTree();
```

---

## 🔍 حالات استخدام شائعة

### 1. قائمة منسدلة للفئات

```javascript
async function buildCategorySelect(tenantId) {
  const tree = await getCategoryTree(tenantId, 'ar');
  const select = document.getElementById('job-category');
  
  function addOptions(categories, prefix = '') {
    categories.forEach(cat => {
      const option = document.createElement('option');
      option.value = cat.id;
      option.textContent = prefix + cat.name;
      select.appendChild(option);
      
      if (cat.children.length > 0) {
        addOptions(cat.children, prefix + '— ');
      }
    });
  }
  
  addOptions(tree);
}
```

### 2. Breadcrumb Navigation

```javascript
async function getCategoryPath(tenantId, categoryId) {
  let path = [];
  let current = await getCategory(tenantId, categoryId);
  
  while (current) {
    path.unshift(current);
    if (current.parent_id) {
      current = await getCategory(tenantId, current.parent_id);
    } else {
      current = null;
    }
  }
  
  return path;
}

// عرض
const path = await getCategoryPath(1, 5);
const breadcrumb = path.map(c => c.name).join(' > ');
console.log(breadcrumb); // "تقنية المعلومات > تطوير البرمجيات"
```

---

## ⚠️ ملاحظات مهمة

1. **الفئات الهرمية:** يمكن إنشاء أي عدد من المستويات
2. **الحذف:** لا يمكن حذف فئة لها فئات فرعية
3. **النقل:** لا يمكن نقل فئة إلى نفسها أو لأحد أطفالها
4. **الترتيب:** `sort_order` يحدد ترتيب العرض
5. **Slug:** يتم توليده تلقائياً من `name` إذا لم يُحدد
6. **الترجمات:** يجب توفير ترجمة واحدة على الأقل عند الإنشاء

---

## 🎯 Best Practices

1. **استخدم الشجرة للعرض:** أفضل من جلب القائمة المسطحة
2. **احفظ الترجمات دفعة واحدة:** استخدم `bulk_translations`
3. **استخدم `sort_order`:** للتحكم في ترتيب العرض
4. **استخدم Caching:** للشجرة الكاملة
5. **Validation:** تحقق من البيانات قبل الإرسال

---

**آخر تحديث:** فبراير 2026