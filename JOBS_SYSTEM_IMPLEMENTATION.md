# 🎯 Jobs Management System - Implementation Guide

## نظام إدارة الوظائف - دليل التنفيذ الشامل

---

## 📋 Overview / نظرة عامة

This document provides comprehensive information about the newly implemented Jobs Management System for the qooqz platform, including job listings and job categories with full multi-language support.

تم تنفيذ نظام إدارة وظائف متكامل يشمل إدارة الوظائف وفئات الوظائف مع دعم كامل لتعدد اللغات.

---

## 📁 Files Created / الملفات المنشأة

### Job Categories Module / وحدة فئات الوظائف

1. **`/admin/fragments/job_categories.php`** (419 lines)
   - Main admin interface for job categories management
   - واجهة الإدارة الرئيسية لإدارة فئات الوظائف

2. **`/admin/assets/js/pages/job_categories.js`** (837 lines)
   - JavaScript logic for CRUD operations
   - منطق JavaScript لعمليات الإنشاء والقراءة والتحديث والحذف

3. **`/admin/assets/css/pages/job_categories.css`** (700 lines)
   - Styling for job categories interface
   - التنسيقات الخاصة بواجهة فئات الوظائف

### Jobs Module / وحدة الوظائف

4. **`/admin/fragments/jobs.php`** (772 lines)
   - Main admin interface for jobs management
   - واجهة الإدارة الرئيسية لإدارة الوظائف

5. **`/admin/assets/js/pages/jobs.js`** (1,277 lines)
   - Complete JavaScript module for jobs
   - وحدة JavaScript الكاملة للوظائف

6. **`/admin/assets/css/pages/jobs.css`** (722 lines)
   - Comprehensive styling for jobs interface
   - تنسيقات شاملة لواجهة الوظائف

**Total: 3,727 lines of production-ready code**

---

## 🗄️ Database Schema / مخطط قاعدة البيانات

### Job Categories Tables / جداول فئات الوظائف

#### `job_categories`
```sql
- id (bigint)
- tenant_id (int unsigned) - للمستأجر
- parent_id (bigint) - الفئة الأب (هرمية)
- slug (varchar 255)
- sort_order (int) - ترتيب العرض
- is_active (tinyint) - نشط/غير نشط
- created_at (datetime)
```

#### `job_category_translations`
```sql
- id (bigint)
- category_id (bigint)
- language_code (varchar 8) - ar, en, fr, etc.
- name (varchar 255) - اسم الفئة
- description (text) - الوصف
```

### Jobs Tables / جداول الوظائف

#### `jobs` (26 fields)
```sql
Basic Information:
- id, entity_id, job_title, slug, job_type, employment_type
- experience_level, category, department, positions_available

Application:
- application_form_type (simple/custom/external)
- external_application_url
- application_deadline

Salary:
- salary_min, salary_max, salary_currency
- salary_period (hourly/daily/weekly/monthly/yearly)
- salary_negotiable

Location:
- country_id, city_id, work_location
- is_remote

Status & Counters:
- status (draft/published/closed/filled/cancelled)
- views_count, applications_count
- is_featured, is_urgent

Timestamps:
- created_by, created_at, updated_at
- published_at, closed_at, start_date
```

#### `job_translations`
```sql
- id, job_id, language_code
- job_title - عنوان الوظيفة
- description - الوصف الكامل
- requirements - المتطلبات
- responsibilities - المسؤوليات
- benefits - المزايا
```

#### `job_skills`
```sql
- id, job_id
- skill_name - اسم المهارة
- proficiency_level (basic/intermediate/advanced/expert)
- is_required - إلزامية/اختيارية
```

---

## 🎨 Features / الميزات

### Job Categories / فئات الوظائف

#### ✅ Hierarchical Structure / البنية الهرمية
- Parent-child category relationships
- علاقات الفئة الأب والفئة الفرعية
- Unlimited nesting levels
- مستويات تداخل غير محدودة

#### ✅ Multi-Language Support / دعم تعدد اللغات
- Translation management for each category
- إدارة الترجمات لكل فئة
- Name and description in multiple languages
- الاسم والوصف بعدة لغات

#### ✅ Media Integration / تكامل الوسائط
- Image type integration (image_types.id=11)
- تكامل نوع الصورة
- Media Studio for image management
- استوديو الوسائط لإدارة الصور

#### ✅ Advanced Management / إدارة متقدمة
- Custom sort ordering
- ترتيب مخصص للعرض
- Active/inactive status
- حالة نشط/غير نشط
- Bulk operations
- عمليات جماعية

### Jobs Management / إدارة الوظائف

#### ✅ Comprehensive Job Form / نموذج وظيفة شامل

**Basic Information Tab:**
- Job title and slug
- عنوان الوظيفة والرابط المختصر
- Job type (full-time, part-time, contract, etc.)
- نوع الوظيفة (دوام كامل، جزئي، عقد، إلخ)
- Employment type (permanent, temporary, seasonal)
- نوع التوظيف (دائم، مؤقت، موسمي)
- Experience level (entry, junior, mid, senior, executive)
- مستوى الخبرة
- Category and department
- الفئة والقسم
- Positions available
- عدد المناصب المتاحة

**Translations Tab:**
- Job title translation
- ترجمة عنوان الوظيفة
- Full description
- الوصف الكامل
- Requirements (qualifications, education, certifications)
- المتطلبات (المؤهلات، التعليم، الشهادات)
- Responsibilities (daily tasks, duties)
- المسؤوليات (المهام اليومية، الواجبات)
- Benefits (insurance, vacation, bonuses)
- المزايا (التأمين، الإجازات، المكافآت)

**Application Tab:**
- Application form type selection
- نوع نموذج التقديم
- External application URL (if applicable)
- رابط التقديم الخارجي
- Application deadline with date picker
- الموعد النهائي للتقديم

**Salary Tab:**
- Minimum and maximum salary
- الحد الأدنى والأقصى للراتب
- Currency selection (SAR, USD, EUR, etc.)
- اختيار العملة
- Pay period (hourly, daily, weekly, monthly, yearly)
- فترة الدفع
- Salary negotiable checkbox
- خانة الراتب قابل للتفاوض

**Location Tab:**
- Country and city selection
- اختيار البلد والمدينة
- Specific work location address
- عنوان موقع العمل المحدد
- Remote work option
- خيار العمل عن بعد

**Skills Tab:**
- Add/edit/remove job skills
- إضافة/تعديل/حذف مهارات الوظيفة
- Proficiency level for each skill
- مستوى الكفاءة لكل مهارة
- Mark skills as required or optional
- تحديد المهارات كإلزامية أو اختيارية

**Status & Flags Tab:**
- Status workflow (draft → published → closed/filled/cancelled)
- سير عمل الحالة
- Featured job flag (highlighted in listings)
- علامة الوظيفة المميزة
- Urgent job flag (priority display)
- علامة الوظيفة العاجلة
- Start date
- تاريخ البدء
- Published date (auto-set when published)
- تاريخ النشر

#### ✅ Status Workflow / سير عمل الحالة

```
draft (مسودة)
    ↓ Publish / نشر
published (منشورة)
    ↓ Close / إغلاق
closed (مغلقة)
    ↓ Mark as Filled / تحديد كمملوءة
filled (مملوءة)

Or Cancel at any time / أو إلغاء في أي وقت
    → cancelled (ملغية)
```

#### ✅ Advanced Filtering / الفلترة المتقدمة

- Search by job title
- البحث بعنوان الوظيفة
- Filter by status
- فلترة بالحالة
- Filter by job type
- فلترة بنوع الوظيفة
- Filter by experience level
- فلترة بمستوى الخبرة
- Filter by category
- فلترة بالفئة
- Filter by entity
- فلترة بالكيان

---

## 🔐 Permissions System / نظام الصلاحيات

### Required Permissions / الصلاحيات المطلوبة

#### For Job Categories:
```sql
- job_categories.manage (full access)
- job_categories.create
- job_categories.view
- job_categories.edit
- job_categories.delete
```

#### For Jobs:
```sql
- jobs.manage (full access)
- jobs.create
- jobs.view (can_view_all, can_view_tenant, can_view_own)
- jobs.edit (can_edit_all, can_edit_own)
- jobs.delete (can_delete_all, can_delete_own)
```

### Access Levels / مستويات الوصول

1. **Super Admin / السوبر أدمن**
   - Full access to all jobs and categories across all tenants
   - وصول كامل لجميع الوظائف والفئات لجميع المستأجرين

2. **Tenant Admin / مدير المستأجر**
   - Access to jobs within their tenant
   - الوصول للوظائف ضمن المستأجر الخاص بهم
   - Manage job categories for their tenant
   - إدارة فئات الوظائف للمستأجر

3. **Entity User / مستخدم الكيان**
   - Access to jobs within their entity only
   - الوصول للوظائف ضمن الكيان الخاص بهم فقط
   - Can create and manage their own entity's jobs
   - يمكن إنشاء وإدارة وظائف الكيان الخاص بهم

---

## 🚀 Getting Started / البدء

### 1. Access the Interface / الوصول للواجهة

#### Job Categories:
```
URL: /admin/fragments/job_categories.php
أو من قائمة الإدارة: Jobs → Job Categories
```

#### Jobs Management:
```
URL: /admin/fragments/jobs.php
أو من قائمة الإدارة: Jobs → Manage Jobs
```

### 2. Setup Job Categories / إعداد فئات الوظائف

1. Click "Add Category" / انقر "إضافة فئة"
2. Fill basic information (name, slug, parent)
3. Add translations for multiple languages
4. Upload category image (optional)
5. Set sort order and activate
6. Save

### 3. Create a Job Posting / إنشاء إعلان وظيفة

1. Click "Add New Job" / انقر "إضافة وظيفة جديدة"
2. **Basic Info Tab:**
   - Enter job title
   - Select job type and employment type
   - Choose experience level
   - Select category and department
3. **Translations Tab:**
   - Add descriptions in multiple languages
   - Fill requirements and responsibilities
   - List benefits
4. **Application Tab:**
   - Set application deadline
   - Choose form type or external URL
5. **Salary Tab:**
   - Enter salary range
   - Select currency and period
6. **Location Tab:**
   - Choose country and city
   - Add specific location
   - Enable remote if applicable
7. **Skills Tab:**
   - Add required skills
   - Set proficiency levels
8. **Status Tab:**
   - Choose status (draft/published)
   - Set featured/urgent flags
9. Save and publish

---

## 🌍 Multi-Language Support / دعم تعدد اللغات

### Supported Languages / اللغات المدعومة

The system loads available languages from `/api/languages`:
- Arabic (العربية) - RTL
- English - LTR
- French (Français) - LTR
- Hebrew (עברית) - RTL
- Urdu (اردو) - RTL
- And any other languages configured in the system

### Translation Management / إدارة الترجمات

1. **Add Translation:**
   - Select language from dropdown
   - Click "Add Translation"
   - Fill translation fields
   - Save

2. **Edit Translation:**
   - Click edit icon next to translation
   - Modify fields
   - Save changes

3. **Delete Translation:**
   - Click delete icon
   - Confirm deletion

### RTL Support / دعم RTL

The system automatically detects RTL languages (ar, he, fa, ur) and:
- Changes page direction
- Adjusts layouts
- Flips icons and alignments
- Applies RTL-specific styling

---

## 🎨 UI Components / مكونات واجهة المستخدم

### Job Categories Interface:

**List View:**
- Hierarchical tree display
- Parent-child indicators
- Search and filter bar
- Sort order display
- Active/inactive badges
- Action buttons (edit, delete)

**Form Tabs:**
1. Basic Information
2. Translations
3. Media (images)

### Jobs Interface:

**List View:**
- Comprehensive job cards
- Status badges with colors
- Featured/urgent indicators
- Quick actions (edit, delete, change status)
- Advanced filters panel
- Pagination controls

**Form Tabs:**
1. Basic Information
2. Translations (5 fields)
3. Application Settings
4. Salary Details
5. Location & Remote
6. Skills Management
7. Status & Flags

---

## 📡 API Integration / تكامل API

### Endpoints Used / نقاط النهاية المستخدمة

```javascript
// Job Categories
GET    /api/job_categories              // List categories
GET    /api/job_categories/:id          // Get category
POST   /api/job_categories              // Create category
PUT    /api/job_categories/:id          // Update category
DELETE /api/job_categories/:id          // Delete category

// Jobs
GET    /api/jobs                        // List jobs
GET    /api/jobs/:id                    // Get job
POST   /api/jobs                        // Create job
PUT    /api/jobs/:id                    // Update job
DELETE /api/jobs/:id                    // Delete job

// Supporting APIs
GET    /api/languages                   // Get available languages
GET    /api/image-types                 // Get image types
GET    /api/job_skills                  // Manage job skills
```

### Request/Response Format / صيغة الطلب/الاستجابة

**Create Job Example:**
```json
{
  "job_title": "Senior Full Stack Developer",
  "slug": "senior-full-stack-developer",
  "job_type": "full_time",
  "employment_type": "permanent",
  "experience_level": "senior",
  "category": "technology",
  "department": "engineering",
  "positions_available": 2,
  "salary_min": 15000,
  "salary_max": 25000,
  "salary_currency": "SAR",
  "salary_period": "monthly",
  "application_deadline": "2024-12-31",
  "is_remote": 1,
  "status": "published",
  "translations": [
    {
      "language_code": "ar",
      "job_title": "مطور متكامل أول",
      "description": "...",
      "requirements": "...",
      "responsibilities": "...",
      "benefits": "..."
    }
  ],
  "skills": [
    {
      "skill_name": "JavaScript",
      "proficiency_level": "expert",
      "is_required": 1
    }
  ]
}
```

---

## 🔧 Customization / التخصيص

### Modify Job Types / تعديل أنواع الوظائف

Edit the enum in database:
```sql
ALTER TABLE jobs MODIFY job_type 
ENUM('full_time','part_time','contract','temporary','internship','freelance','remote','custom_type');
```

### Add Custom Status / إضافة حالة مخصصة

```sql
ALTER TABLE jobs MODIFY status 
ENUM('draft','published','closed','filled','cancelled','on_hold','under_review');
```

### Customize UI Colors / تخصيص ألوان الواجهة

Edit in `/admin/assets/css/pages/jobs.css`:
```css
/* Status colors */
.badge-published { background: #10b981; }
.badge-draft { background: #6b7280; }
.badge-closed { background: #ef4444; }
.badge-filled { background: #3b82f6; }
```

---

## 🐛 Troubleshooting / حل المشاكل

### Issue: Categories not loading / المشكلة: الفئات لا تحمل

**Solution:**
1. Check API endpoint: `/api/job_categories`
2. Verify database tables exist
3. Check permissions in browser console
4. Ensure user has view permissions

### Issue: Translations not saving / المشكلة: الترجمات لا تحفظ

**Solution:**
1. Verify `job_translations` table exists
2. Check language codes match `/api/languages`
3. Verify CSRF token is valid
4. Check browser console for errors

### Issue: Images not displaying / المشكلة: الصور لا تظهر

**Solution:**
1. Ensure Media Studio is integrated
2. Verify image_types.id=11 exists
3. Check file upload permissions
4. Verify image URLs are correct

### Issue: Permission denied / المشكلة: الصلاحية مرفوضة

**Solution:**
1. Check user role permissions
2. Verify resource_permissions table
3. Ensure super admin status if needed
4. Check admin_context.php is loading

---

## 📊 Performance Optimization / تحسين الأداء

### Recommended Indexes / الفهارس الموصى بها

```sql
-- Job categories
CREATE INDEX idx_jc_tenant ON job_categories(tenant_id);
CREATE INDEX idx_jc_parent ON job_categories(parent_id);
CREATE INDEX idx_jc_active ON job_categories(is_active);

-- Jobs
CREATE INDEX idx_jobs_entity ON jobs(entity_id);
CREATE INDEX idx_jobs_status ON jobs(status);
CREATE INDEX idx_jobs_type ON jobs(job_type);
CREATE INDEX idx_jobs_level ON jobs(experience_level);
CREATE INDEX idx_jobs_deadline ON jobs(application_deadline);
CREATE INDEX idx_jobs_featured ON jobs(is_featured);

-- Translations
CREATE INDEX idx_jt_job_lang ON job_translations(job_id, language_code);
CREATE INDEX idx_jct_cat_lang ON job_category_translations(category_id, language_code);

-- Skills
CREATE INDEX idx_jskills_job ON job_skills(job_id);
```

### Caching Strategy / استراتيجية التخزين المؤقت

```javascript
// Cache languages for 1 hour
AF.Cache.set('languages', languages, 3600);

// Cache categories for 30 minutes
AF.Cache.set('job_categories', categories, 1800);
```

---

## 📱 Mobile Responsive / استجابة الأجهزة المحمولة

The interface is fully responsive with breakpoints:

- **Desktop (1024px+)**: Full multi-column layout
- **Tablet (768px)**: Adjusted column widths, stacked tabs
- **Mobile (480px)**: Single column, touch-optimized buttons

---

## ✅ Testing Checklist / قائمة الاختبار

### Job Categories:
- [ ] Create category
- [ ] Add parent-child relationship
- [ ] Add translations in multiple languages
- [ ] Upload category image
- [ ] Edit category
- [ ] Delete category
- [ ] Reorder categories
- [ ] Filter and search

### Jobs:
- [ ] Create job with all tabs filled
- [ ] Add job translations
- [ ] Add job skills
- [ ] Change job status
- [ ] Mark job as featured
- [ ] Set application deadline
- [ ] Test remote job option
- [ ] Edit existing job
- [ ] Delete job
- [ ] Filter jobs by status
- [ ] Search jobs

### Permissions:
- [ ] Test as super admin
- [ ] Test as tenant admin
- [ ] Test as entity user
- [ ] Verify access restrictions

---

## 📚 Additional Resources / موارد إضافية

### Documentation Files:
- `README_JOB_CATEGORIES.md` - Job Categories API documentation
- `jobsREADME.md` - Jobs API documentation
- `job_skills.md` - Job Skills documentation
- `job_application.md` - Job Applications documentation

### Related Modules:
- Job Applications Management
- Job Interviews Scheduling
- Job Alerts System
- Application Questions/Answers

---

## 🎓 Best Practices / أفضل الممارسات

### 1. Translation Management:
- Always provide translations for primary languages (ar, en)
- Keep translations consistent across jobs
- Use professional, clear language
- Review translations before publishing

### 2. Job Posting:
- Use descriptive job titles
- Provide detailed requirements
- Be clear about salary ranges
- Set realistic deadlines
- Update status promptly

### 3. Category Organization:
- Keep hierarchy simple (2-3 levels max)
- Use clear, searchable names
- Group related categories
- Maintain consistent naming

### 4. Performance:
- Use filters to limit results
- Archive old/filled jobs
- Regular database cleanup
- Optimize images before upload

---

## 📧 Support / الدعم

For questions or issues:
1. Check this documentation
2. Review API documentation
3. Check browser console for errors
4. Contact system administrator

---

## 🔄 Version History / سجل الإصدارات

**Version 1.0.0** (Current)
- Initial implementation
- Job categories with translations
- Jobs management with full features
- Multi-language support
- Permission-based access control
- Media integration
- Skills management
- Status workflow

---

**Last Updated:** February 15, 2026
**Status:** Production Ready ✅
