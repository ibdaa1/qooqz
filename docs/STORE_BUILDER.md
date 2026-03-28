# QOOQZ Store Builder Documentation

## نظام بناء صفحة المتجر الديناميكي

> وثيقة مرجعية كاملة لنظام بناء صفحات المتاجر (Store Page Builder)

---

## 📁 File Map (خريطة الملفات)

### Database
| File | Description |
|------|-------------|
| `database/migrations/create_store_pages_sections.sql` | Migration: store_pages + store_sections + store_section_translations |

### Frontend — Main Page
| File | Description |
|------|-------------|
| `frontend/public/entity.php` | Dynamic store page renderer — loads sections from DB, falls back to defaults |

### Frontend — Section Partials
| File | Section Type | Description |
|------|-------------|-------------|
| `frontend/partials/store_sections/header.php` | `header` | Cover image, logo, name, rating, verification badge, open/closed |
| `frontend/partials/store_sections/contact.php` | `contact` | Phone, email, website, social links, share button |
| `frontend/partials/store_sections/tabs.php` | `tabs` | Tab navigation bar |
| `frontend/partials/store_sections/products.php` | `products` | Product grid with categories, search, pagination, add-to-cart |
| `frontend/partials/store_sections/info.php` | `info` | Description, attributes, payment methods, business settings |
| `frontend/partials/store_sections/hours.php` | `hours` | Working hours schedule with open/closed logic |
| `frontend/partials/store_sections/location.php` | `location` | Map links (OpenStreetMap + Google Maps), addresses |
| `frontend/partials/store_sections/offers.php` | `offers` | Discounts, promotions, copy codes |
| `frontend/partials/store_sections/reviews.php` | `reviews` | Rating summary, review cards, submit form |

---

## 🗄️ Database Schema

### Table: `store_pages`
One page per entity. Links to the entity for which the page is created.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment |
| `tenant_id` | INT | Tenant isolation |
| `entity_id` | BIGINT | Entity (vendor) this page belongs to |
| `type` | VARCHAR(50) | Page type: `store`, `landing`, etc. |
| `slug` | VARCHAR(255) | Optional custom URL slug |
| `is_active` | TINYINT(1) | 1 = active, 0 = disabled |
| `settings` | JSON | Page-level settings (theme, colors) |
| `created_at` | TIMESTAMP | Auto |
| `updated_at` | TIMESTAMP | Auto |

**Unique Key:** `(tenant_id, entity_id, type)`

### Table: `store_sections`
Ordered sections within a page. Each section has a type, position, and JSON settings.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment |
| `page_id` | BIGINT FK | References `store_pages.id` |
| `type` | VARCHAR(50) | Section type (see list below) |
| `position` | INT | Sort order (lower = first) |
| `is_active` | TINYINT(1) | 1 = active, 0 = hidden |
| `settings` | JSON | Section-specific configuration |
| `created_at` | TIMESTAMP | Auto |
| `updated_at` | TIMESTAMP | Auto |

**FK:** `page_id → store_pages.id ON DELETE CASCADE`

### Table: `store_section_translations`
Multi-language content for each section.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT PK | Auto-increment |
| `section_id` | BIGINT FK | References `store_sections.id` |
| `language_code` | VARCHAR(10) | e.g., `en`, `ar` |
| `title` | VARCHAR(255) | Localized title |
| `content` | JSON | Localized content data |

**Unique Key:** `(section_id, language_code)`

---

## 📦 Section Types & Settings

### 1. `header` — Store Header
Cover image banner, logo overlay, store name, rating stars, verification badge, open/closed status.

**Settings JSON:**
```json
{
  "show_cover": true,
  "show_rating": true,
  "show_verified": true,
  "show_status": true
}
```

**Layout (similar to Google Maps / Talabat):**
```
┌─────────────────────────────────────┐
│          COVER IMAGE (full width)   │
│                                     │
│  ┌──────┐                           │
│  │ LOGO │  Store Name ⭐ 4.5 (120) │
│  └──────┘  ✅ Verified  🟢 Open    │
│            Restaurant • Fast Food   │
│            Store description...     │
└─────────────────────────────────────┘
```

### 2. `contact` — Contact Information
Phone, email, website links, social media buttons, share functionality.

**Settings JSON:**
```json
{
  "show_phone": true,
  "show_email": true,
  "show_website": true,
  "show_share": true,
  "show_social": true
}
```

### 3. `tabs` — Tab Navigation
Configurable tab bar. Tabs only show if their corresponding section is active.

**Settings JSON:**
```json
{
  "tabs": ["products", "info", "hours", "location", "offers", "reviews"]
}
```

### 4. `products` — Product Grid
Product cards with category filters, search, pagination, add-to-cart.

**Settings JSON:**
```json
{
  "per_page": 12,
  "show_categories": true,
  "show_search": true,
  "show_cart": true
}
```

### 5. `info` — Business Information
Entity attributes, payment methods, business settings (min order, delivery radius, etc.).

**Settings JSON:**
```json
{
  "show_description": true,
  "show_attributes": true,
  "show_payment_methods": true,
  "show_settings": true
}
```

### 6. `hours` — Working Hours
Daily schedule with open/closed logic calculated from current time.

**Settings JSON:**
```json
{}
```

**Open/Closed Logic:**
```php
$nowDow  = (int)date('w');                  // 0(Sun)…6(Sat)
$nowMins = (int)date('H') * 60 + (int)date('i');
foreach ($workingHours as $h) {
    if ($h['day_of_week'] !== $nowDow) continue;
    if (!$h['is_open']) → CLOSED
    if ($nowMins >= openMin && $nowMins < closeMin) → OPEN
    else → CLOSED
}
```

### 7. `location` — Map & Addresses
Address cards with links to OpenStreetMap and Google Maps.

**Settings JSON:**
```json
{
  "show_osm": true,
  "show_google": true
}
```

### 8. `offers` — Discounts & Promotions
Discount cards with codes, marketing badges, terms, expiry dates.

**Settings JSON:**
```json
{}
```

### 9. `reviews` — Ratings & Reviews
Rating summary, review list, star picker, submit form (login-gated).

**Settings JSON:**
```json
{
  "show_form": true,
  "limit": 5
}
```

---

## ⚙️ Dynamic Section Rendering

### How it works

1. **Load sections from DB:**
   ```sql
   SELECT ss.id, ss.type, ss.position, ss.settings
     FROM store_sections ss
     JOIN store_pages sp ON sp.id = ss.page_id
    WHERE sp.entity_id = ? AND sp.is_active = 1 AND ss.is_active = 1
    ORDER BY ss.position ASC
   ```

2. **Fallback to default order** when no DB config exists:
   ```
   header(10) → contact(20) → tabs(30) → products(40) → info(50)
   → hours(60) → location(70) → offers(80) → reviews(90)
   ```

3. **Render sections** via partial templates:
   ```php
   foreach ($storeSections as $section) {
       $sectionType     = $section['type'];
       $sectionSettings = json_decode($section['settings'], true) ?: [];
       $sectionFile     = $sectionDir . '/' . basename($sectionType) . '.php';
       if (file_exists($sectionFile)) {
           include $sectionFile;
       }
   }
   ```

### Adding a new section type

1. Create `frontend/partials/store_sections/{type}.php`
2. Add the type to the default sections array in `entity.php`
3. Insert a row in `store_sections` for entities that should use it

---

## 🔧 Sample SQL — Creating a Store Page

```sql
-- Create a store page for entity ID 1
INSERT INTO store_pages (tenant_id, entity_id, type)
VALUES (1, 1, 'store');

-- Add sections (default order)
INSERT INTO store_sections (page_id, type, position, is_active, settings) VALUES
  (1, 'header',   10, 1, '{"show_cover": true, "show_rating": true, "show_verified": true, "show_status": true}'),
  (1, 'contact',  20, 1, '{"show_phone": true, "show_email": true, "show_website": true, "show_share": true, "show_social": true}'),
  (1, 'tabs',     30, 1, '{"tabs": ["products","info","hours","location","offers","reviews"]}'),
  (1, 'products', 40, 1, '{"per_page": 12, "show_categories": true, "show_search": true, "show_cart": true}'),
  (1, 'info',     50, 1, '{"show_description": true, "show_attributes": true, "show_payment_methods": true, "show_settings": true}'),
  (1, 'hours',    60, 1, '{}'),
  (1, 'location', 70, 1, '{"show_osm": true, "show_google": true}'),
  (1, 'offers',   80, 1, '{}'),
  (1, 'reviews',  90, 1, '{"show_form": true, "limit": 5}');
```

### Disable a section
```sql
UPDATE store_sections SET is_active = 0 WHERE page_id = 1 AND type = 'offers';
```

### Reorder sections
```sql
UPDATE store_sections SET position = 15 WHERE page_id = 1 AND type = 'contact';
-- Now: header(10) → contact(15) → tabs(30) → ...
```

---

## 🌐 Multi-Language Support

Translations are stored in `store_section_translations`:

```sql
INSERT INTO store_section_translations (section_id, language_code, title, content) VALUES
  (1, 'ar', 'رأس الصفحة', '{}'),
  (1, 'en', 'Store Header', '{}');
```

The frontend uses the `t()` helper for UI labels and `entity_translations` for entity-specific text (store name, description).

**RTL Support:** The `$dir` variable is set from `$ctx['dir']` and CSS uses logical properties (`margin-inline-start`, etc.).

---

## 📱 Mobile-First Design

All section templates use responsive CSS:

```css
/* Default: mobile layout */
.pub-entity-profile-header { flex-direction: column; }
.pub-entity-profile-logo { width: 72px; height: 72px; }

/* Desktop: side-by-side layout */
@media (min-width: 600px) {
    .pub-entity-profile-header { flex-direction: row; gap: 20px; }
    .pub-entity-profile-logo { width: 96px; height: 96px; }
}
```

---

## 🏢 Multi-Tenant Isolation

- `store_pages.tenant_id` ensures each tenant's pages are isolated
- Entity queries include `tenant_id` filtering
- Product queries filter by `p.tenant_id`
- All data is scoped to the entity's tenant

---

## 🔔 Notification System — Logo & Site Data in FCM

When sending push notifications, the FCM payload now includes:

### Image/Icon URLs
- `notification.image` — Large image in notification body (custom via `$data['image_url']` or `APP_LOGO_URL`)
- `webpush.notification.icon` — Notification icon (custom via `$data['icon_url']` or `APP_LOGO_URL`)
- `webpush.notification.badge` — Badge icon (always `APP_LOGO_URL`)
- `webpush.notification.image` — Web notification image

### Site Data in FCM `data` payload
Every notification includes:
- `site_name` — App name (from `APP_NAME` constant or `'QOOQZ'`)
- `site_url` — App URL (from `APP_URL` constant or env)
- `icon_url` — Notification icon URL
- `image_url` — Notification image URL
- `click_url` — Where to navigate on click

### Sending a notification with custom logo
```php
Notification::send(
    recipientId: $userId,
    recipientType: 'user',
    tenantId: $tenantId,
    typeCode: 'general',
    title: 'New Order',
    message: 'You have a new order from Store XYZ',
    data: [
        'icon_url'  => 'https://example.com/logos/store-xyz.png',
        'image_url' => 'https://example.com/covers/store-xyz-banner.jpg',
        'click_url' => '/frontend/public/orders.php?id=123',
    ],
    channels: ['database', 'push']
);
```

### Configuration
In `api/shared/config/.env`:
```
APP_URL=https://your-domain.com
APP_NOTIFICATION_ICON=/path/to/your/logo.png
```

`APP_LOGO_URL` is built as: `APP_URL + APP_NOTIFICATION_ICON`
Example: `https://your-domain.com/admin/assets/img/default-image.png`

> ⚠️ **Important:** `APP_LOGO_URL` must be an absolute HTTPS URL for FCM icons to display correctly.

---

## 📋 Data Flow Overview

```
User visits /frontend/public/entity.php?id=123
    │
    ├─→ Load entity data from DB (entities, entity_translations, images)
    ├─→ Load working_hours, addresses, payment_methods, attributes
    ├─→ Load products with categories
    ├─→ Load discounts
    ├─→ Load ratings/reviews
    ├─→ Calculate open/closed status
    │
    ├─→ Load store_sections from DB (or use defaults)
    │
    └─→ Dynamic Section Renderer
         ├─→ header.php    → Banner + Logo + Name + Rating + Status
         ├─→ contact.php   → Phone + Email + Social + Share
         ├─→ tabs.php      → Tab navigation
         ├─→ products.php  → Product grid + Categories + Pagination
         ├─→ info.php      → Attributes + Payment + Settings
         ├─→ hours.php     → Working hours schedule
         ├─→ location.php  → Map links + Addresses
         ├─→ offers.php    → Discount cards
         └─→ reviews.php   → Ratings + Review form
```

---

## 🔒 Security Notes

- All user output is escaped via `e()` helper (htmlspecialchars)
- SQL queries use prepared statements with bound parameters
- File includes use `basename()` to prevent path traversal
- Review submission is login-gated via `$_isLoggedIn`
- Entity visibility controlled via `entity_settings.is_visible`
- Maintenance mode controlled via `entity_settings.maintenance_mode`

---

## 📝 Existing Database Tables Used

| Table | Purpose |
|-------|---------|
| `entities` | Main entity/vendor data |
| `entity_translations` | Localized store_name, description |
| `entity_settings` | Visibility, maintenance, business config |
| `entities_working_hours` | Daily schedule (day_of_week, open_time, close_time) |
| `images` | Logo and cover images (owner_id = entity.id) |
| `addresses` | Entity addresses with lat/lng |
| `entity_payment_methods` | Accepted payment methods |
| `entities_attributes` / `entities_attribute_values` | Custom attributes |
| `entity_ratings` | User ratings and reviews |
| `products` / `product_translations` | Entity products |
| `product_pricing` | Product prices |
| `categories` / `category_translations` | Product categories |
| `discounts` / `discount_translations` | Promotions and offers |
| `seo_meta` | SEO metadata per entity |

---

*Last updated: 2026-03-28*
