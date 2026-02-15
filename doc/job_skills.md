# 📋 Job Skills API - دليل الاستخدام الشامل

نظام إدارة مهارات الوظائف مع دعم مستويات الإتقان والمهارات المطلوبة/الاختيارية.

---

## 📁 هيكل الملفات

```
job-skills/
├── repositories/
│   └── PdoJobSkillsRepository.php               # إدارة المهارات
├── validators/
│   └── JobSkillsValidator.php                   # التحقق من البيانات
├── services/
│   └── JobSkillsService.php                     # منطق الأعمال
├── controllers/
│   └── JobSkillsController.php                  # التحكم بالطلبات
└── api/routes/job_skills.php                    # نقطة الدخول الرئيسية
```

---

## 🌟 الميزات الرئيسية

- ✅ **إدارة مهارات الوظائف** مع مستويات الإتقان
- ✅ **تصنيف المهارات** (مطلوبة/اختيارية)
- ✅ **عمليات جماعية** (إنشاء، تحديث، حذف متعدد)
- ✅ **نسخ المهارات** من وظيفة إلى أخرى
- ✅ **فلترة متقدمة** (بالوظيفة، المستوى، البحث)
- ✅ **إدارة حسب الوظيفة** مع ترتيب أبجدي
- ✅ **تحقق شامل** من صحة البيانات
- ✅ **دعم الصفحات** والترتيب

---

## 📊 بنية البيانات

### حقول المهارة:

| الحقل | النوع | مطلوب | الوصف |
|------|------|-------|-------|
| `id` | bigint | تلقائي | معرف المهارة |
| `job_id` | bigint | ✅ | معرف الوظيفة |
| `skill_name` | varchar(100) | ✅ | اسم المهارة |
| `proficiency_level` | enum | افتراضي: intermediate | basic/intermediate/advanced/expert |
| `is_required` | boolean | افتراضي: 1 | مطلوب (1) أو اختياري (0) |

---

## 📡 API Endpoints

### 1. قائمة المهارات (List Skills)

**الطلب:**
```http
GET /api/job_skills?user_id=1
```

**المعاملات:**

| المعامل | النوع | الافتراضي | الوصف |
|---------|------|-----------|-------|
| `user_id` | integer | - | **مطلوب** - معرف المستخدم |
| `page` | integer | 1 | رقم الصفحة |
| `limit` | integer | 25 | عدد النتائج (max: 1000) |
| `order_by` | string | 'skill_name' | الترتيب حسب |
| `order_dir` | string | 'ASC' | اتجاه الترتيب |

**الفلاتر:**

| الفلتر | النوع | الوصف |
|--------|------|-------|
| `id` | integer | معرف المهارة |
| `job_id` | integer | معرف الوظيفة |
| `proficiency_level` | string | مستوى الإتقان |
| `is_required` | integer | مطلوب (0/1) |
| `search` | string | البحث في اسم المهارة |

**مثال - مهارات وظيفة معينة:**
```http
GET /api/job_skills?user_id=1&job_id=5
```

**مثال - المهارات المطلوبة فقط:**
```http
GET /api/job_skills?user_id=1&job_id=5&is_required=1
```

**مثال - البحث:**
```http
GET /api/job_skills?user_id=1&search=php
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "job_id": 5,
        "skill_name": "PHP",
        "proficiency_level": "advanced",
        "is_required": 1,
        "job_slug": "senior-php-developer"
      },
      {
        "id": 2,
        "job_id": 5,
        "skill_name": "MySQL",
        "proficiency_level": "intermediate",
        "is_required": 0,
        "job_slug": "senior-php-developer"
      }
    ],
    "meta": {
      "total": 2,
      "page": 1,
      "per_page": 25,
      "total_pages": 1,
      "from": 1,
      "to": 2
    }
  }
}
```

---

### 2. مهارات وظيفة معينة (Get Skills by Job)

**الطلب:**
```http
GET /api/job_skills?user_id=1&job_id=5
```

**المعاملات الإضافية:**
- `required_only=1`: للحصول على المهارات المطلوبة فقط

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "skills": [
      {
        "id": 1,
        "job_id": 5,
        "skill_name": "PHP",
        "proficiency_level": "advanced",
        "is_required": 1
      }
    ]
  }
}
```

---

### 3. مهارة واحدة (Get Single Skill)

**الطلب:**
```http
GET /api/job_skills?user_id=1&id=5
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "job_id": 10,
    "skill_name": "JavaScript",
    "proficiency_level": "intermediate",
    "is_required": 1,
    "job_slug": "frontend-developer"
  }
}
```

---

### 4. إنشاء مهارة جديدة (Create Skill)

**الطلب:**
```http
POST /api/job_skills?user_id=1
Content-Type: application/json
```

**البيانات:**
```json
{
  "job_id": 5,
  "skill_name": "React.js",
  "proficiency_level": "intermediate",
  "is_required": 1
}
```

**الحقول الاختيارية:**
- `proficiency_level`: افتراضياً `intermediate`
- `is_required`: افتراضياً `1`

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "id": 15
  },
  "message": "Skill created"
}
```

---

### 5. تحديث مهارة (Update Skill)

**الطلب:**
```http
PUT /api/job_skills?user_id=1
Content-Type: application/json
```

**البيانات:**
```json
{
  "id": 15,
  "skill_name": "React.js",
  "proficiency_level": "advanced",
  "is_required": 1
}
```

---

### 6. حذف مهارة (Delete Skill)

**الطلب:**
```http
DELETE /api/job_skills?user_id=1
Content-Type: application/json

{
  "id": 15
}
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "deleted": true
  },
  "message": "Skill deleted"
}
```

---

### 7. حذف جميع مهارات الوظيفة (Delete Skills by Job)

**الطلب:**
```http
DELETE /api/job_skills?user_id=1&delete_by_job=1&job_id=5
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "deleted": true
  },
  "message": "Skills deleted for job"
}
```

---

### 8. نسخ المهارات من وظيفة أخرى (Duplicate Skills)

**الطلب:**
```http
PATCH /api/job_skills?user_id=1&duplicate_from_job=1&source_job_id=5&target_job_id=10
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "duplicated": true
  },
  "message": "Skills duplicated"
}
```

---

### 9. تحديث جماعي لمهارات الوظيفة (Bulk Update Skills)

**الطلب:**
```http
PATCH /api/job_skills?user_id=1&bulk_update=1&job_id=5
Content-Type: application/json

{
  "skills": [
    {
      "skill_name": "PHP",
      "proficiency_level": "advanced",
      "is_required": 1
    },
    {
      "skill_name": "Laravel",
      "proficiency_level": "intermediate",
      "is_required": 0
    }
  ]
}
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "updated": true
  },
  "message": "Skills updated"
}
```

---

### 10. الحصول على مستويات الإتقان (Get Proficiency Levels)

**الطلب:**
```http
GET /api/job_skills?user_id=1&proficiency_levels=1
```

**الاستجابة:**
```json
{
  "success": true,
  "data": {
    "proficiency_levels": ["basic", "intermediate", "advanced", "expert"]
  }
}
```

---

## 💡 أمثلة الاستخدام بـ JavaScript

### 1. الحصول على مهارات الوظيفة

```javascript
async function getJobSkills(userId, jobId, requiredOnly = false) {
  const params = new URLSearchParams({
    user_id: userId,
    job_id: jobId
  });

  if (requiredOnly) {
    params.append('required_only', '1');
  }

  const response = await fetch(`/api/job_skills?${params}`);
  const data = await response.json();
  
  if (data.success) {
    return data.data.skills;
  }
  throw new Error(data.message);
}

// استخدام
getJobSkills(1, 5, true)
  .then(skills => {
    console.log('Required skills:', skills);
  });
```

### 2. إنشاء مهارة جديدة

```javascript
async function createSkill(userId, skillData) {
  const response = await fetch(`/api/job_skills?user_id=${userId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(skillData)
  });

  const data = await response.json();
  
  if (!data.success) {
    throw new Error(data.message);
  }
  
  return data.data.id;
}

// استخدام
createSkill(1, {
  job_id: 5,
  skill_name: 'Vue.js',
  proficiency_level: 'intermediate',
  is_required: 1
}).then(newId => {
  console.log('Created skill ID:', newId);
});
```

### 3. تحديث جماعي للمهارات

```javascript
async function bulkUpdateSkills(userId, jobId, skills) {
  const response = await fetch(
    `/api/job_skills?user_id=${userId}&bulk_update=1&job_id=${jobId}`,
    {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ skills })
    }
  );

  return await response.json();
}

// استخدام
bulkUpdateSkills(1, 5, [
  { skill_name: 'PHP', proficiency_level: 'advanced', is_required: 1 },
  { skill_name: 'MySQL', proficiency_level: 'intermediate', is_required: 0 }
]).then(result => {
  console.log('Bulk update:', result.data.updated);
});
```

### 4. نسخ المهارات

```javascript
async function duplicateSkills(userId, sourceJobId, targetJobId) {
  const response = await fetch(
    `/api/job_skills?user_id=${userId}&duplicate_from_job=1&source_job_id=${sourceJobId}&target_job_id=${targetJobId}`,
    { method: 'PATCH' }
  );

  return await response.json();
}

// استخدام
duplicateSkills(1, 5, 10).then(result => {
  console.log('Duplicated:', result.data.duplicated);
});
```

### 5. حذف مهارة

```javascript
async function deleteSkill(userId, skillId) {
  const response = await fetch(`/api/job_skills?user_id=${userId}`, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ id: skillId })
  });

  return await response.json();
}

// استخدام
deleteSkill(1, 15).then(result => {
  console.log('Deleted:', result.data.deleted);
});
```

---

## 🎨 مثال عملي - واجهة مستخدم

### HTML Structure
```html
<div id="skills-manager">
  <div class="toolbar">
    <select id="job-selector">
      <option value="">اختر الوظيفة</option>
    </select>
    <button onclick="loadSkills()">تحديث المهارات</button>
    <button onclick="showBulkForm()">تحديث جماعي</button>
    <button onclick="duplicateSkills()">نسخ من وظيفة أخرى</button>
  </div>

  <div class="filters">
    <select id="filter-proficiency">
      <option value="">كل المستويات</option>
      <option value="basic">أساسي</option>
      <option value="intermediate">متوسط</option>
      <option value="advanced">متقدم</option>
      <option value="expert">خبير</option>
    </select>
    
    <select id="filter-required">
      <option value="">الكل</option>
      <option value="1">مطلوب</option>
      <option value="0">اختياري</option>
    </select>
    
    <input type="text" id="search" placeholder="بحث...">
    <button onclick="applyFilters()">بحث</button>
  </div>

  <div id="skills-list" class="skills-container"></div>

  <!-- Bulk Update Form -->
  <div id="bulk-form" class="modal">
    <h3>تحديث مهارات الوظيفة</h3>
    <textarea id="skills-textarea" placeholder="skill_name,proficiency_level,is_required&#10;PHP,advanced,1&#10;MySQL,intermediate,0" rows="10"></textarea>
    <button onclick="saveBulkSkills()">حفظ</button>
    <button onclick="closeBulkForm()">إلغاء</button>
  </div>
</div>
```

### JavaScript Implementation
```javascript
class JobSkillsManager {
  constructor(userId) {
    this.userId = userId;
    this.selectedJobId = null;
    this.currentFilters = {};
  }

  async loadJobs() {
    // Load jobs for selector - assuming jobs API exists
    const response = await fetch(`/api/jobs?user_id=${this.userId}`);
    const data = await response.json();
    
    const selector = document.getElementById('job-selector');
    selector.innerHTML = '<option value="">اختر الوظيفة</option>';
    
    data.data.items.forEach(job => {
      const option = document.createElement('option');
      option.value = job.id;
      option.textContent = job.title;
      selector.appendChild(option);
    });

    selector.addEventListener('change', (e) => {
      this.selectedJobId = e.target.value;
      if (this.selectedJobId) {
        this.loadSkills();
      }
    });
  }

  async loadSkills() {
    if (!this.selectedJobId) return;

    const params = new URLSearchParams({
      user_id: this.userId,
      job_id: this.selectedJobId,
      ...this.currentFilters
    });

    const response = await fetch(`/api/job_skills?${params}`);
    const data = await response.json();
    
    if (data.success) {
      this.renderSkills(data.data.items);
    }
  }

  renderSkills(skills) {
    const container = document.getElementById('skills-list');
    container.innerHTML = '';

    skills.forEach(skill => {
      const item = document.createElement('div');
      item.className = 'skill-item';
      item.innerHTML = `
        <div class="skill-header">
          <h4>${skill.skill_name}</h4>
          <span class="badge proficiency ${skill.proficiency_level}">
            ${this.getProficiencyLabel(skill.proficiency_level)}
          </span>
          <span class="badge ${skill.is_required ? 'required' : 'optional'}">
            ${skill.is_required ? 'مطلوب' : 'اختياري'}
          </span>
        </div>
        <div class="skill-actions">
          <button onclick="manager.editSkill(${skill.id})">تعديل</button>
          <button onclick="manager.deleteSkill(${skill.id})" class="danger">حذف</button>
        </div>
      `;
      
      container.appendChild(item);
    });
  }

  getProficiencyLabel(level) {
    const labels = {
      'basic': 'أساسي',
      'intermediate': 'متوسط',
      'advanced': 'متقدم',
      'expert': 'خبير'
    };
    return labels[level] || level;
  }

  async deleteSkill(skillId) {
    if (!confirm('هل أنت متأكد من حذف هذه المهارة؟')) return;

    const response = await fetch(`/api/job_skills?user_id=${this.userId}`, {
      method: 'DELETE',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id: skillId })
    });

    const data = await response.json();
    
    if (data.success) {
      this.loadSkills();
    }
  }

  async showBulkForm() {
    if (!this.selectedJobId) {
      alert('اختر وظيفة أولاً');
      return;
    }

    // Load current skills
    const response = await fetch(`/api/job_skills?user_id=${this.userId}&job_id=${this.selectedJobId}`);
    const data = await response.json();
    
    if (data.success) {
      const textarea = document.getElementById('skills-textarea');
      const skillsText = data.data.items.map(skill => 
        `${skill.skill_name},${skill.proficiency_level},${skill.is_required}`
      ).join('\n');
      
      textarea.value = skillsText;
      document.getElementById('bulk-form').style.display = 'block';
    }
  }

  async saveBulkSkills() {
    const textarea = document.getElementById('skills-textarea');
    const lines = textarea.value.trim().split('\n');
    
    const skills = [];
    for (const line of lines) {
      const parts = line.split(',');
      if (parts.length >= 3) {
        skills.push({
          skill_name: parts[0].trim(),
          proficiency_level: parts[1].trim(),
          is_required: parseInt(parts[2].trim())
        });
      }
    }

    const response = await fetch(
      `/api/job_skills?user_id=${this.userId}&bulk_update=1&job_id=${this.selectedJobId}`,
      {
        method: 'PATCH',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ skills })
      }
    );

    const data = await response.json();
    
    if (data.success) {
      document.getElementById('bulk-form').style.display = 'none';
      this.loadSkills();
    } else {
      alert(data.message);
    }
  }

  async duplicateSkills() {
    const sourceJobId = prompt('معرف الوظيفة المصدر:');
    if (!sourceJobId || !this.selectedJobId) return;

    const response = await fetch(
      `/api/job_skills?user_id=${this.userId}&duplicate_from_job=1&source_job_id=${sourceJobId}&target_job_id=${this.selectedJobId}`,
      { method: 'PATCH' }
    );

    const data = await response.json();
    
    if (data.success) {
      this.loadSkills();
    }
  }

  applyFilters() {
    const proficiency = document.getElementById('filter-proficiency').value;
    const isRequired = document.getElementById('filter-required').value;
    const search = document.getElementById('search').value;

    this.currentFilters = {};
    if (proficiency) this.currentFilters.proficiency_level = proficiency;
    if (isRequired !== '') this.currentFilters.is_required = isRequired;
    if (search) this.currentFilters.search = search;

    this.loadSkills();
  }
}

// Initialize
const manager = new JobSkillsManager(1);
manager.loadJobs();
```

---

## ⚠️ ملاحظات مهمة

1. **الصلاحيات:** يجب التحقق من صلاحية المستخدم للوظيفة قبل إجراء العمليات
2. **التحقق من البيانات:** جميع المدخلات يتم التحقق منها في طبقة الـ Validator
3. **الترتيب الافتراضي:** أبجدي حسب اسم المهارة
4. **القيم الافتراضية:** proficiency_level = 'intermediate', is_required = 1
5. **الحد الأقصى للأسماء:** skill_name لا يتجاوز 100 حرف
6. **العمليات الجماعية:** تحذف المهارات القديمة وتضيف الجديدة

---

## 🎯 Best Practices

1. **استخدم مستويات الإتقان بحكمة:** حدد المستوى المناسب للوظيفة
2. **صنف المهارات:** افصل بين المطلوب والاختياري
3. **استخدم التحديث الجماعي:** لإدارة مهارات وظيفة كاملة
4. **انسخ من وظائف مشابهة:** لتوفير الوقت
5. **راجع المهارات دورياً:** احذف أو حدث حسب الحاجة
6. **استخدم البحث:** للعثور على مهارات محددة بسرعة

---

**آخر تحديث:** فبراير 2026