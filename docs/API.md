# TORO — توثيق واجهة برمجة التطبيقات (API Reference)

> **النسخة:** v1  
> **القاعدة:** `/toro/api/`  
> **الترميز:** UTF-8 · JSON فقط (Content-Type: application/json)  
> **المصادقة:** JWT Bearer — `Authorization: Bearer <token>`

---

## جدول المحتويات

1. [البنية العامة](#البنية-العامة)
2. [المصادقة — Auth](#المصادقة--auth)
3. [المستخدمون — Users](#المستخدمون--users)
4. [عناوين المستخدم — UserAddresses](#عناوين-المستخدم--useraddresses)
5. [حسابات OAuth — UserSocialAccounts](#حسابات-oauth--usersocialaccounts)
6. [رموز المستخدم — UserTokens](#رموز-المستخدم--usertokens)
7. [الأدوار والصلاحيات — Roles & Permissions](#الأدوار-والصلاحيات--roles--permissions)
8. [اللغات — Languages](#اللغات--languages)
9. [الترجمات — Translations](#الترجمات--translations)
10. [الإعدادات — Settings](#الإعدادات--settings)
11. [السمة — Theme](#السمة--theme)
12. [الأحجام — ThemeSizes](#الأحجام--themesizes)
13. [التصنيفات — Categories](#التصنيفات--categories)
14. [العلامات التجارية — Brands](#العلامات-التجارية--brands)
15. [المنتجات — Products](#المنتجات--products)
16. [قيم المنتج — ProductAttributeValues](#قيم-المنتج--productattributevalues)
17. [متغيرات المنتج — ProductVariants](#متغيرات-المنتج--productvariants)
18. [مراجعات المنتج — ProductReviews](#مراجعات-المنتج--productreviews)
19. [الصفات — Attributes](#الصفات--attributes)
20. [الصور — Images](#الصور--images)
21. [البانرات — Banners](#البانرات--banners)
22. [الصفحات — Pages](#الصفحات--pages)
23. [الكوبونات — Coupons](#الكوبونات--coupons)
24. [سلة التسوق — Carts](#سلة-التسوق--carts)
25. [الطلبات — Orders](#الطلبات--orders)
26. [عناوين الشحن — OrderShippingAddresses](#عناوين-الشحن--ordershippingaddresses)
27. [المدفوعات — Payments](#المدفوعات--payments)
28. [القوائم — Menus](#القوائم--menus)
29. [قوائم المفضلة — Wishlists](#قوائم-المفضلة--wishlists)
30. [الإشعارات — Notifications](#الإشعارات--notifications)
31. [سجلات التدقيق — AuditLogs](#سجلات-التدقيق--auditlogs)
32. [رموز CSRF — CsrfTokens](#رموز-csrf--csrftokens)
33. [حدود المعدل — RateLimits](#حدود-المعدل--ratelimits)
34. [المسارات العامة — Public](#المسارات-العامة--public)
35. [مسارات لوحة الإدارة — Admin](#مسارات-لوحة-الإدارة--admin)
36. [قواعد البيانات — Database Schema](#قواعد-البيانات--database-schema)
37. [أكواد SQL — حذف الأعمدة القديمة](#أكواد-sql--حذف-الأعمدة-القديمة)

---

## البنية العامة

### هيكل المشروع

```
toro/api/
├── bootstrap.php              # نقطة الدخول — تهيئة البيئة
├── index.php                  # index يستدعي bootstrap
├── shared/
│   ├── core/
│   │   ├── Kernel.php         # Router + Middleware Runner
│   │   ├── DatabaseConnection.php
│   │   └── Response.php
│   ├── config/
│   │   └── db.php
│   ├── domain/exceptions/     # NotFoundException, ValidationException, …
│   └── helpers/
│       └── env_helper.php
└── v1/
    ├── middleware/             # AuthMiddleware, AdminMiddleware, …
    ├── modules/                # 35+ وحدة
    └── routes/                 # ملف مسار لكل وحدة
```

### صيغة الاستجابة

```json
{
  "success": true,
  "data": { … },
  "message": "…",
  "meta": { "total": 100, "page": 1, "per_page": 20 }
}
```

**أكواد HTTP المستخدمة:**

| كود | المعنى |
|-----|--------|
| 200 | نجاح (GET / PUT / PATCH) |
| 201 | تم الإنشاء (POST) |
| 204 | لا محتوى (DELETE) |
| 400 | بيانات غير صالحة |
| 401 | غير مصادق |
| 403 | غير مصرح |
| 404 | غير موجود |
| 409 | تعارض (duplicate) |
| 422 | خطأ تحقق |
| 429 | تجاوز حد المعدل |
| 500 | خطأ داخلي |

### middleware المتاحة

| Middleware | الوصف |
|------------|-------|
| `AuthMiddleware` | يتحقق من JWT Bearer token |
| `AdminMiddleware` | يتحقق من صلاحية المسؤول (role_id ≠ 4) |
| `GuestMiddleware` | يرفض إذا كان المستخدم مسجل دخول |
| `ThrottleMiddleware:N,W` | N طلب كل W ثانية |
| `CsrfMiddleware` | يتحقق من CSRF token للطلبات غير-GET |

---

## المصادقة — Auth

**المسارات الأساسية: `/v1/auth/*`**

### POST /v1/auth/register
تسجيل مستخدم جديد.

**Body:**
```json
{
  "first_name": "أحمد",
  "last_name": "العلي",
  "email": "ahmed@example.com",
  "password": "SecurePass1!",
  "phone": "+966501234567",
  "language": "ar"
}
```

**Response 201:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ…",
    "refresh_token": "…",
    "user": { "id": 1, "email": "…", "role": "customer" }
  }
}
```

---

### POST /v1/auth/login
تسجيل الدخول بالبريد وكلمة المرور.

**Body:**
```json
{ "email": "ahmed@example.com", "password": "SecurePass1!" }
```

**Response 200:** مثل `/register`

---

### POST /v1/auth/logout
تسجيل الخروج (يُلغي refresh token). يتطلب `AuthMiddleware`.

---

### POST /v1/auth/refresh
تجديد JWT بواسطة refresh token.

**Body:**
```json
{ "refresh_token": "…" }
```

---

### GET /v1/auth/me
بيانات المستخدم الحالي. يتطلب `AuthMiddleware`.

---

### POST /v1/auth/change-password
تغيير كلمة المرور. يتطلب `AuthMiddleware`.

**Body:**
```json
{ "current_password": "…", "new_password": "…" }
```

---

### POST /v1/auth/forgot-password
إرسال رابط إعادة تعيين كلمة المرور.

**Body:**
```json
{ "email": "ahmed@example.com" }
```

---

### POST /v1/auth/reset-password
إعادة تعيين كلمة المرور.

**Body:**
```json
{ "token": "…", "new_password": "…" }
```

---

### POST /v1/auth/oauth/google
تسجيل الدخول بـ Google.

**Body:**
```json
{ "token": "<google_id_token_or_access_token>" }
```

---

### POST /v1/auth/oauth/facebook
تسجيل الدخول بـ Facebook.

**Body:**
```json
{ "token": "<facebook_access_token>" }
```

---

### GET /v1/auth/verify-email/{token}
التحقق من البريد الإلكتروني.

---

## المستخدمون — Users

**يتطلب `AdminMiddleware` للإدارة — `AuthMiddleware` للمستخدم نفسه**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/users` | قائمة المستخدمين (Admin) |
| GET | `/v1/users/{id}` | مستخدم محدد (Admin) |
| POST | `/v1/users` | إنشاء مستخدم (Admin) |
| PUT | `/v1/users/{id}` | تحديث مستخدم (Admin) |
| DELETE | `/v1/users/{id}` | حذف مستخدم — soft delete (Admin) |
| POST | `/v1/users/{id}/restore` | استرجاع مستخدم محذوف (Admin) |
| GET | `/v1/users/me` | بيانات المستخدم الحالي |
| PUT | `/v1/users/me` | تحديث بيانات المستخدم الحالي |

**حقول GET Response:**
```json
{
  "id": 1,
  "first_name": "أحمد",
  "last_name": "العلي",
  "email": "ahmed@example.com",
  "phone": "+966501234567",
  "role_id": 4,
  "role_slug": "customer",
  "language_id": 1,
  "is_active": 1,
  "email_verified_at": "2025-01-01 10:00:00",
  "created_at": "…"
}
```

> **ملاحظة:** لا يوجد حقل `avatar` — الصور تُدار عبر جدول `images` الموحد بـ `owner_type='user'`.

---

## عناوين المستخدم — UserAddresses

**`/v1/user-addresses/*` — يتطلب `AuthMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/user-addresses` | عناوين المستخدم الحالي |
| GET | `/v1/user-addresses/{id}` | عنوان محدد |
| POST | `/v1/user-addresses` | إضافة عنوان |
| PUT | `/v1/user-addresses/{id}` | تحديث عنوان |
| DELETE | `/v1/user-addresses/{id}` | حذف عنوان |
| PATCH | `/v1/user-addresses/{id}/set-default` | تعيين كعنوان افتراضي |

**Body (POST/PUT):**
```json
{
  "label": "Home",
  "full_name": "أحمد العلي",
  "phone": "+966501234567",
  "country_id": 1,
  "city_id": 5,
  "district": "الملز",
  "address_line1": "شارع الأمير محمد",
  "address_line2": "مبنى 12",
  "postal_code": "11564",
  "is_default": false
}
```

> **ملاحظة DB:** الجدول يستخدم `country_id` (FK → `countries.id`) و`city_id` (FK → `cities.id`) بدلاً من `country_code` و`city` نص.
>
> **أوامر SQL للتحديث:**
> ```sql
> ALTER TABLE user_addresses
>   DROP COLUMN country_code,
>   DROP COLUMN city,
>   ADD COLUMN country_id INT(11) NOT NULL AFTER phone,
>   ADD COLUMN city_id    INT(11) NOT NULL AFTER country_id,
>   ADD CONSTRAINT fk_addr_country FOREIGN KEY (country_id) REFERENCES countries(id),
>   ADD CONSTRAINT fk_addr_city    FOREIGN KEY (city_id)    REFERENCES cities(id);
> ```

---

## حسابات OAuth — UserSocialAccounts

**يتطلب `AuthMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/user-social-accounts` | حسابات OAuth المرتبطة |
| DELETE | `/v1/user-social-accounts/{id}` | فك ربط حساب |

---

## رموز المستخدم — UserTokens

**يتطلب `AdminMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/user-tokens` | قائمة الرموز |
| DELETE | `/v1/user-tokens/{id}` | حذف رمز |

---

## الأدوار والصلاحيات — Roles & Permissions

### Roles — `/v1/roles/*` (Admin)

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/roles` | قائمة الأدوار |
| GET | `/v1/roles/{id}` | دور محدد |
| POST | `/v1/roles` | إنشاء دور |
| PUT | `/v1/roles/{id}` | تحديث دور |
| DELETE | `/v1/roles/{id}` | حذف دور |

**Body (POST/PUT):**
```json
{ "name": "editor", "slug": "editor", "description": "محرر المحتوى" }
```

### Permissions — `/v1/permissions/*` (Admin)

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/permissions` | قائمة الصلاحيات |
| GET | `/v1/permissions/{id}` | صلاحية محددة |
| POST | `/v1/permissions` | إنشاء صلاحية |
| PUT | `/v1/permissions/{id}` | تحديث صلاحية |
| DELETE | `/v1/permissions/{id}` | حذف صلاحية |

### RolePermissions — `/v1/role-permissions/*` (Admin)

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/role-permissions/{role_id}` | صلاحيات دور |
| POST | `/v1/role-permissions/{role_id}/attach` | إضافة صلاحيات |
| POST | `/v1/role-permissions/{role_id}/detach` | إزالة صلاحيات |
| POST | `/v1/role-permissions/{role_id}/sync` | مزامنة الصلاحيات كاملاً |

---

## اللغات — Languages

**`/v1/languages/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/languages` | — | قائمة اللغات النشطة |
| GET | `/v1/languages/{id}` | Admin | لغة محددة |
| POST | `/v1/languages` | Admin | إنشاء لغة |
| PUT | `/v1/languages/{id}` | Admin | تحديث لغة |
| DELETE | `/v1/languages/{id}` | Admin | حذف لغة |

**Body (POST/PUT):**
```json
{ "code": "ar", "name": "العربية", "direction": "rtl", "is_default": true, "is_active": true }
```

---

## الترجمات — Translations

**`/v1/translations/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/translations` | — | قائمة مفاتيح الترجمة |
| GET | `/v1/translations/{lang}` | — | ترجمات لغة بعينها |
| POST | `/v1/translations` | Admin | إنشاء مفتاح ترجمة |
| PUT | `/v1/translations/{id}` | Admin | تحديث قيمة ترجمة |
| DELETE | `/v1/translations/{id}` | Admin | حذف مفتاح ترجمة |

---

## الإعدادات — Settings

**`/v1/settings/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/settings` | — | جميع الإعدادات العامة |
| GET | `/v1/settings/{key}` | — | إعداد محدد |
| POST | `/v1/settings` | Admin | إنشاء/تحديث إعداد |
| PUT | `/v1/settings/{id}` | Admin | تحديث إعداد |
| DELETE | `/v1/settings/{id}` | Admin | حذف إعداد |

---

## السمة — Theme

**`/v1/theme/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/theme` | — | إعدادات السمة |
| GET | `/v1/theme/css` | — | CSS متغيرات السمة |
| POST | `/v1/theme` | Admin | إنشاء إعداد سمة |
| PUT | `/v1/theme/{id}` | Admin | تحديث إعداد سمة |
| DELETE | `/v1/theme/{id}` | Admin | حذف إعداد سمة |

---

## الأحجام — ThemeSizes

**`/v1/theme-sizes/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/theme-sizes` | — | قائمة الأحجام (XS/S/M/L/XL) |
| GET | `/v1/theme-sizes/{id}` | — | حجم محدد |
| POST | `/v1/theme-sizes` | Admin | إنشاء حجم |
| PUT | `/v1/theme-sizes/{id}` | Admin | تحديث حجم |
| DELETE | `/v1/theme-sizes/{id}` | Admin | حذف حجم |

---

## التصنيفات — Categories

**`/v1/categories/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/categories` | — | قائمة التصنيفات |
| GET | `/v1/categories/{id}` | — | تصنيف بالمعرف |
| GET | `/v1/categories/slug/{slug}` | — | تصنيف بالـ slug |
| POST | `/v1/categories` | Admin | إنشاء تصنيف |
| PUT | `/v1/categories/{id}` | Admin | تحديث تصنيف |
| DELETE | `/v1/categories/{id}` | Admin | حذف تصنيف |

**Query Params (GET /v1/categories):**
`?lang=ar&parent_id=1&is_active=1&limit=50&offset=0`

**Body (POST/PUT):**
```json
{
  "slug": "electronics",
  "parent_id": null,
  "sort_order": 1,
  "is_active": true,
  "translations": [
    { "lang": "ar", "name": "الإلكترونيات", "description": "…", "meta_title": "…", "meta_desc": "…" },
    { "lang": "en", "name": "Electronics",  "description": "…" }
  ]
}
```

> **ملاحظة:** لا يوجد حقل `image` — الصور تُدار عبر جدول `images` الموحد بـ `owner_type='category'`.

---

## العلامات التجارية — Brands

**`/v1/brands/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/brands` | — | قائمة العلامات |
| GET | `/v1/brands/{id}` | — | علامة بالمعرف |
| GET | `/v1/brands/slug/{slug}` | — | علامة بالـ slug |
| POST | `/v1/brands` | Admin | إنشاء علامة |
| PUT | `/v1/brands/{id}` | Admin | تحديث علامة |
| DELETE | `/v1/brands/{id}` | Admin | حذف علامة |

**Body (POST/PUT):**
```json
{
  "slug": "samsung",
  "website": "https://samsung.com",
  "sort_order": 1,
  "is_active": true,
  "translations": [
    { "lang": "ar", "name": "سامسونج", "description": "…" },
    { "lang": "en", "name": "Samsung",  "description": "…" }
  ]
}
```

> **ملاحظة:** لا يوجد حقل `logo` — الشعار يُدار عبر جدول `images` الموحد بـ `owner_type='brand'`.

---

## المنتجات — Products

**`/v1/products/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/products` | — | قائمة المنتجات |
| GET | `/v1/products/{id}` | — | منتج بالمعرف |
| GET | `/v1/products/sku/{sku}` | — | منتج بالـ SKU |
| POST | `/v1/products` | Admin | إنشاء منتج |
| PUT | `/v1/products/{id}` | Admin | تحديث منتج |
| DELETE | `/v1/products/{id}` | Admin | حذف ناعم |
| POST | `/v1/products/{id}/restore` | Admin | استرجاع منتج |

**Query Params:** `?lang=ar&category_id=1&brand_id=2&type=simple&min_price=10&max_price=500&in_stock=1&limit=20&offset=0&search=…`

**Body (POST/PUT):**
```json
{
  "sku": "ELEC-001",
  "category_id": 1,
  "brand_id": 2,
  "type": "simple",
  "base_price": 299.99,
  "sale_price": 249.99,
  "stock_qty": 100,
  "sort_order": 1,
  "is_active": true,
  "translations": [
    { "lang": "ar", "name": "هاتف سامسونج", "description": "…", "meta_title": "…", "meta_desc": "…" },
    { "lang": "en", "name": "Samsung Phone", "description": "…" }
  ]
}
```

> `type`: `simple | variable | digital | bundle`

---

## قيم المنتج — ProductAttributeValues

**`/v1/products/{product_id}/attribute-values`**

| Method | Path | Auth |
|--------|------|------|
| GET | `/v1/products/{id}/attribute-values` | — |
| POST | `/v1/products/{id}/attribute-values` | Admin |
| DELETE | `/v1/products/{id}/attribute-values/{av_id}` | Admin |

---

## متغيرات المنتج — ProductVariants

**`/v1/products/{product_id}/variants`**

| Method | Path | Auth |
|--------|------|------|
| GET | `/v1/products/{id}/variants` | — |
| POST | `/v1/products/{id}/variants` | Admin |
| PUT | `/v1/products/{id}/variants/{variant_id}` | Admin |
| DELETE | `/v1/products/{id}/variants/{variant_id}` | Admin |

---

## مراجعات المنتج — ProductReviews

**`/v1/products/{product_id}/reviews`**

| Method | Path | Auth |
|--------|------|------|
| GET | `/v1/products/{id}/reviews` | — |
| POST | `/v1/products/{id}/reviews` | Auth |
| PUT | `/v1/reviews/{id}` | Auth (owner) |
| DELETE | `/v1/reviews/{id}` | Auth (owner/Admin) |

---

## الصفات — Attributes

**`/v1/attributes/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/attributes` | — | قائمة الصفات |
| GET | `/v1/attributes/{id}` | — | صفة محددة |
| POST | `/v1/attributes` | Admin | إنشاء صفة |
| PUT | `/v1/attributes/{id}` | Admin | تحديث |
| DELETE | `/v1/attributes/{id}` | Admin | حذف |
| GET | `/v1/attributes/{id}/values` | — | قيم الصفة |
| POST | `/v1/attributes/{id}/values` | Admin | إضافة قيمة |
| PUT | `/v1/attributes/{id}/values/{val_id}` | Admin | تحديث قيمة |
| DELETE | `/v1/attributes/{id}/values/{val_id}` | Admin | حذف قيمة |

**Body (POST attribute):**
```json
{ "slug": "color", "type": "color", "sort_order": 1, "is_active": true }
```
> `type`: `color | size | select | boolean | text`

---

## الصور — Images

**`/v1/images/*`**

جدول الصور الموحد يربط الصور بأي كيان عبر `owner_type + owner_id`.

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/images` | Admin | قائمة الصور |
| GET | `/v1/images/{id}` | Auth | صورة محددة |
| POST | `/v1/images` | Admin | رفع صورة بـ URL |
| POST | `/v1/images/upload` | Admin | رفع ملف صورة |
| PATCH | `/v1/images/{id}/set-main` | Admin | تعيين كصورة رئيسية |
| DELETE | `/v1/images/{id}` | Admin | حذف صورة |
| GET | `/v1/image-types` | — | أنواع الصور |
| POST | `/v1/image-types` | Admin | إنشاء نوع |
| PUT | `/v1/image-types/{id}` | Admin | تحديث نوع |
| DELETE | `/v1/image-types/{id}` | Admin | حذف نوع |

**Body (POST /v1/images):**
```json
{
  "owner_type": "product",
  "owner_id": 5,
  "image_type_id": 1,
  "url": "https://cdn.example.com/img.jpg",
  "alt_text": "صورة المنتج",
  "sort_order": 0,
  "is_main": false
}
```

**Owner Types المدعومة:**
`product | category | brand | banner | user | page`

---

## البانرات — Banners

**`/v1/banners/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/banners` | — | قائمة البانرات |
| GET | `/v1/banners/{id}` | — | بانر محدد |
| POST | `/v1/banners` | Admin | إنشاء بانر |
| PUT | `/v1/banners/{id}` | Admin | تحديث |
| DELETE | `/v1/banners/{id}` | Admin | حذف |

> لا توجد حقول `image` أو `mobile_image` في الجدول — الصور تُدار عبر جدول `images`.

---

## الصفحات — Pages

**`/v1/pages/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/pages` | — | قائمة الصفحات |
| GET | `/v1/pages/{id}` | — | صفحة بالمعرف |
| GET | `/v1/pages/slug/{slug}` | — | صفحة بالـ slug |
| POST | `/v1/pages` | Admin | إنشاء صفحة |
| PUT | `/v1/pages/{id}` | Admin | تحديث |
| DELETE | `/v1/pages/{id}` | Admin | حذف |

---

## الكوبونات — Coupons

**`/v1/coupons/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/coupons` | Admin | قائمة الكوبونات |
| GET | `/v1/coupons/{id}` | Admin | كوبون محدد |
| POST | `/v1/coupons` | Admin | إنشاء كوبون |
| PUT | `/v1/coupons/{id}` | Admin | تحديث |
| DELETE | `/v1/coupons/{id}` | Admin | حذف |
| POST | `/v1/coupons/validate` | Auth | التحقق من صلاحية كوبون |

---

## سلة التسوق — Carts

**`/v1/carts/*` — يتطلب `AuthMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/carts` | عرض السلة |
| POST | `/v1/carts/items` | إضافة منتج |
| PUT | `/v1/carts/items/{id}` | تحديث الكمية |
| DELETE | `/v1/carts/items/{id}` | إزالة منتج |
| DELETE | `/v1/carts` | تفريغ السلة |

---

## الطلبات — Orders

**`/v1/orders/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/orders` | Auth | طلبات المستخدم |
| GET | `/v1/orders/{id}` | Auth | طلب محدد |
| POST | `/v1/orders` | Auth | إنشاء طلب |
| PATCH | `/v1/orders/{id}/cancel` | Auth | إلغاء طلب |
| GET | `/v1/orders` | Admin | جميع الطلبات |
| PATCH | `/v1/orders/{id}/status` | Admin | تغيير حالة الطلب |

**حالات الطلب:** `pending | confirmed | processing | shipped | delivered | cancelled | refunded`

---

## عناوين الشحن — OrderShippingAddresses

**`/v1/order-shipping-addresses/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/order-shipping-addresses/{order_id}` | Auth | عنوان شحن طلب |
| POST | `/v1/order-shipping-addresses` | Auth | إضافة عنوان شحن |
| PUT | `/v1/order-shipping-addresses/{id}` | Admin | تحديث |

---

## المدفوعات — Payments

**`/v1/payments/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/payments` | Admin | قائمة المدفوعات |
| GET | `/v1/payments/{id}` | Auth | دفعة محددة |
| POST | `/v1/payments` | Auth | إنشاء دفعة |
| PATCH | `/v1/payments/{id}/status` | Admin | تحديث حالة الدفع |

**حالات الدفع:** `pending | paid | failed | refunded | partially_refunded`

---

## القوائم — Menus

**`/v1/menus/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/menus` | — | قائمة القوائم |
| GET | `/v1/menus/{id}` | — | قائمة محددة |
| POST | `/v1/menus` | Admin | إنشاء قائمة |
| PUT | `/v1/menus/{id}` | Admin | تحديث |
| DELETE | `/v1/menus/{id}` | Admin | حذف |

---

## قوائم المفضلة — Wishlists

**`/v1/wishlists/*` — يتطلب `AuthMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/wishlists` | عرض قائمة المفضلة |
| POST | `/v1/wishlists` | إضافة منتج للمفضلة |
| DELETE | `/v1/wishlists/{product_id}` | إزالة من المفضلة |

---

## الإشعارات — Notifications

**`/v1/notifications/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/notifications` | Auth | إشعارات المستخدم |
| PATCH | `/v1/notifications/{id}/read` | Auth | تعليم كمقروء |
| PATCH | `/v1/notifications/read-all` | Auth | تعليم الكل كمقروء |
| DELETE | `/v1/notifications/{id}` | Admin | حذف إشعار |

---

## سجلات التدقيق — AuditLogs

**`/v1/audit-logs/*` — يتطلب `AdminMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/audit-logs` | قائمة سجلات التدقيق |
| GET | `/v1/audit-logs/{id}` | سجل محدد |

**Query Params:** `?actor_id=1&action=product_created&table=products&limit=50&offset=0`

---

## رموز CSRF — CsrfTokens

**`/v1/csrf-tokens/*`**

| Method | Path | Auth | الوصف |
|--------|------|------|-------|
| GET | `/v1/csrf-tokens` | — | الحصول على CSRF token |

---

## حدود المعدل — RateLimits

**`/v1/rate-limits/*` — يتطلب `AdminMiddleware`**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/rate-limits` | عرض حدود المعدل |
| POST | `/v1/rate-limits` | إضافة قاعدة |
| DELETE | `/v1/rate-limits/{id}` | حذف قاعدة |

---

## المسارات العامة — Public

**`/v1/public/*` — بدون مصادقة**

| Method | Path | الوصف |
|--------|------|-------|
| GET | `/v1/public/settings` | الإعدادات العامة |
| GET | `/v1/public/theme` | السمة العامة |
| GET | `/v1/public/theme/css` | CSS السمة |
| GET | `/v1/public/categories` | قائمة التصنيفات |
| GET | `/v1/public/brands` | قائمة العلامات التجارية |
| GET | `/v1/public/products` | قائمة المنتجات |
| GET | `/v1/public/products/{id}` | منتج محدد |
| GET | `/v1/public/banners` | البانرات النشطة |
| GET | `/v1/public/pages/{slug}` | صفحة عامة |
| GET | `/v1/public/menus` | القوائم |
| GET | `/v1/public/languages` | اللغات المتاحة |

---

## مسارات لوحة الإدارة — Admin

**`/v1/admin/*` — يتطلب `AuthMiddleware + AdminMiddleware`**

مسارات مختصرة للوحة الإدارة تُحمّل نفس controllers لكن تحت prefix `/v1/admin/`:

```
/v1/admin/settings         → SettingsController
/v1/admin/theme            → ThemeController
/v1/admin/categories       → CategoriesController
/v1/admin/brands           → BrandsController
/v1/admin/products         → ProductsController
/v1/admin/images           → ImagesController
/v1/admin/users            → UsersController
/v1/admin/orders           → OrdersController
```

---

## قواعد البيانات — Database Schema

### الجداول الأساسية

#### `languages`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT PK | |
| code | CHAR(5) | `ar`, `en` |
| name | VARCHAR(100) | |
| direction | ENUM('ltr','rtl') | |
| is_default | TINYINT(1) | |
| is_active | TINYINT(1) | |

#### `roles`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT UNSIGNED PK | |
| name | VARCHAR(60) | |
| slug | VARCHAR(60) UNIQUE | |
| description | VARCHAR(255) | |

> الأدوار الافتراضية: `1=superadmin, 2=admin, 3=editor, 4=customer`

#### `permissions`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| name | VARCHAR(100) UNIQUE |
| slug | VARCHAR(100) UNIQUE |
| group | VARCHAR(60) |

#### `role_permissions`
| العمود | النوع |
|--------|-------|
| role_id | INT UNSIGNED FK→roles |
| permission_id | INT UNSIGNED FK→permissions |

#### `users`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT UNSIGNED PK | |
| role_id | INT UNSIGNED FK→roles | DEFAULT 4 |
| first_name | VARCHAR(80) | |
| last_name | VARCHAR(80) | |
| email | VARCHAR(180) UNIQUE | |
| email_verified_at | TIMESTAMP | |
| phone | VARCHAR(30) | |
| phone_verified_at | TIMESTAMP | |
| password_hash | VARCHAR(255) | NULL للـ OAuth users |
| language_id | INT UNSIGNED FK→languages | |
| is_active | TINYINT(1) | DEFAULT 1 |
| last_login_at | TIMESTAMP | |
| remember_token | VARCHAR(100) | |
| created_at / updated_at / deleted_at | TIMESTAMP | soft-delete |

> **لا يوجد حقل `avatar`** — يُستخدم جدول `images` بـ `owner_type='user'`

#### `user_addresses`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT UNSIGNED PK | |
| user_id | INT UNSIGNED FK→users | |
| label | VARCHAR(60) | 'Home' default |
| full_name | VARCHAR(120) | |
| phone | VARCHAR(30) | |
| country_id | INT(11) FK→countries | |
| city_id | INT(11) FK→cities | |
| district | VARCHAR(100) | |
| address_line1 | VARCHAR(200) | |
| address_line2 | VARCHAR(200) | |
| postal_code | VARCHAR(20) | |
| is_default | TINYINT(1) | |
| created_at | TIMESTAMP | |

#### `user_social_accounts`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| user_id | INT UNSIGNED FK→users CASCADE |
| provider | ENUM('google','facebook','apple') |
| provider_uid | VARCHAR(200) |
| token | TEXT |
| refresh_token | TEXT |
| expires_at | TIMESTAMP |

#### `user_tokens`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| user_id | INT UNSIGNED FK→users CASCADE |
| token_hash | VARCHAR(255) |
| type | ENUM('refresh','reset_password','verify_email','verify_phone') |
| expires_at | TIMESTAMP |
| used_at | TIMESTAMP |

#### `categories`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT UNSIGNED PK | |
| parent_id | INT UNSIGNED FK→categories | NULL = root |
| slug | VARCHAR(100) UNIQUE | |
| sort_order | SMALLINT | |
| is_active | TINYINT(1) | |
| created_at / updated_at | TIMESTAMP | |

> **لا يوجد حقل `image`** — يُستخدم جدول `images` بـ `owner_type='category'`

#### `category_translations`
| العمود | النوع |
|--------|-------|
| category_id | INT UNSIGNED FK→categories |
| language_id | INT FK→languages |
| name | VARCHAR(200) |
| description | TEXT |
| meta_title | VARCHAR(200) |
| meta_desc | VARCHAR(300) |

#### `brands`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| slug | VARCHAR(100) UNIQUE |
| website | VARCHAR(255) |
| sort_order | SMALLINT |
| is_active | TINYINT(1) |

> **لا يوجد حقل `logo`** — يُستخدم جدول `images` بـ `owner_type='brand'`

#### `products`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| sku | VARCHAR(100) UNIQUE |
| brand_id | INT UNSIGNED FK→brands |
| category_id | INT UNSIGNED FK→categories |
| type | ENUM('simple','variable','digital','bundle') |
| base_price | DECIMAL(12,2) |
| sale_price | DECIMAL(12,2) |
| stock_qty | INT |
| sort_order | SMALLINT |
| is_active | TINYINT(1) |
| deleted_at | TIMESTAMP |

#### `images`
| العمود | النوع | الوصف |
|--------|-------|-------|
| id | INT UNSIGNED PK | |
| owner_type | VARCHAR(50) | `product`, `category`, `brand`, `banner`, `user` |
| owner_id | INT UNSIGNED | معرف الكيان |
| image_type_id | INT UNSIGNED FK→image_types | |
| url | VARCHAR(500) | |
| alt_text | VARCHAR(255) | |
| sort_order | SMALLINT | |
| is_main | TINYINT(1) | |
| created_at | TIMESTAMP | |

#### `theme_sizes`
| العمود | النوع |
|--------|-------|
| id | INT UNSIGNED PK |
| name | VARCHAR(30) UNIQUE — XS/S/M/L/XL |
| sort_order | SMALLINT |
| is_active | TINYINT(1) |

#### `countries`
| العمود | النوع |
|--------|-------|
| id | INT PK |
| iso2 | CHAR(2) |
| iso3 | CHAR(3) |
| name | VARCHAR(200) |
| currency_code | VARCHAR(8) |

#### `cities`
| العمود | النوع |
|--------|-------|
| id | INT PK |
| country_id | INT FK→countries |
| name | VARCHAR(200) |
| state | VARCHAR(200) |
| latitude | DECIMAL(10,7) |
| longitude | DECIMAL(11,7) |
| location | POINT |

---

## أكواد SQL — حذف الأعمدة القديمة

> **⚠️ تشغيل هذه الأوامر بعد نشر الكود الجديد مباشرة.**
> احرص على إنشاء نسخة احتياطية قبل التنفيذ.

```sql
-- 1. حذف عمود الصورة من جدول التصنيفات
ALTER TABLE categories
  DROP COLUMN IF EXISTS image;

-- 2. حذف عمود الصورة الرمزية من جدول المستخدمين
ALTER TABLE users
  DROP COLUMN IF EXISTS avatar;

-- 3. ربط عناوين المستخدم بالدول والمدن
--    (نفذ فقط إذا كانت الأعمدة القديمة موجودة)
ALTER TABLE user_addresses
  DROP FOREIGN KEY IF EXISTS fk_addr_user,  -- أعد إضافته لاحقاً
  DROP COLUMN IF EXISTS country_code,
  DROP COLUMN IF EXISTS city,
  ADD COLUMN country_id INT(11) NOT NULL DEFAULT 1 AFTER phone,
  ADD COLUMN city_id    INT(11) NOT NULL DEFAULT 1 AFTER country_id,
  ADD CONSTRAINT fk_addr_country FOREIGN KEY (country_id) REFERENCES countries(id),
  ADD CONSTRAINT fk_addr_city    FOREIGN KEY (city_id)    REFERENCES cities(id),
  ADD CONSTRAINT fk_addr_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE;

-- 4. تحقق من أن جدول images موجود وصحيح
-- (يُفترض إنشاؤه مسبقاً بـ owner_type + owner_id)
```

---

## متغيرات البيئة المطلوبة (`.env`)

```env
# قاعدة البيانات
DB_HOST=localhost
DB_NAME=qooqz
DB_USER=root
DB_PASS=secret
DB_CHARSET=utf8mb4

# JWT
JWT_SECRET=your_super_secret_key_here
JWT_EXPIRY=3600         # ثواني (1 ساعة)
JWT_REFRESH_EXPIRY=604800  # 7 أيام

# OAuth
GOOGLE_CLIENT_ID=…
FACEBOOK_APP_ID=…
FACEBOOK_APP_SECRET=…

# CSRF
CSRF_SECRET=another_secret

# البيئة
APP_ENV=production      # development | production
APP_URL=https://example.com
```

---

## أمثلة curl

### تسجيل الدخول
```bash
curl -X POST https://api.example.com/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"Admin123!"}'
```

### قائمة المنتجات
```bash
curl "https://api.example.com/v1/products?lang=ar&limit=10&offset=0" \
  -H "Authorization: Bearer <token>"
```

### رفع صورة
```bash
curl -X POST https://api.example.com/v1/images/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@/path/to/image.jpg" \
  -F "owner_type=product" \
  -F "owner_id=5" \
  -F "is_main=1"
```

---

*آخر تحديث: مارس 2026 — TORO API v1*
