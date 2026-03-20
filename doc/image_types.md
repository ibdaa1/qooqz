# Media Studio & Image Types — Complete Technical Documentation

## Table of Contents

1. [Module Overview](#1-module-overview)
2. [Database Schema](#2-database-schema)
   - [`image_types` table](#21-image_types-table)
   - [Seed data — all 21 rows](#22-seed-data--all-21-rows)
3. [Backend File Structure](#3-backend-file-structure)
4. [API Endpoints](#4-api-endpoints)
   - [`/api/image-types`](#41-apiimage-types)
   - [`/api/images`](#42-apiimages)
5. [Admin Frontend — Media Studio](#5-admin-frontend--media-studio)
   - [Fragment `admin/fragments/media_studio.php`](#51-fragment-adminfragmentsmedia_studiophp)
   - [JavaScript `admin/assets/js/pages/media_studio.js`](#52-javascript-adminassetsjspagesmedia_studiojs)
   - [CSS `admin/assets/css/pages/media_studio.css`](#53-css-adminassetscsspagesmedia_studiocss)
6. [DB-Driven Theming (Colors & Fonts)](#6-db-driven-theming-colors--fonts)
7. [DB-Driven Image Type Badges (Icons & Colors)](#7-db-driven-image-type-badges-icons--colors)
8. [Embedding Media Studio in Other Modules](#8-embedding-media-studio-in-other-modules)
9. [Data Flow & Architecture](#9-data-flow--architecture)
10. [Security Notes](#10-security-notes)

---

## 1. Module Overview

The **Media Studio** is a standalone image management tool embedded in the admin panel. It allows administrators to:

- **Upload** images (multiple files, drag-and-drop) with automatic resizing/cropping based on the target `image_type`
- **Browse** all uploaded images with filtering by image type, owner, and visibility
- **Edit** image metadata (filename, URL, visibility, sort order, owner, type)
- **Delete** images
- **Select** images from the library for use in other modules (embedded/select mode)
- **Copy** an existing image path into a new usage (studio copy mode)

Images are typed via the `image_types` table. Every image type defines dimensions, crop mode, quality, format, a **FontAwesome icon**, and a **CSS color badge** — all stored in the database and rendered directly in the UI with no hardcoded values in JavaScript.

---

## 2. Database Schema

### 2.1 `image_types` table

Defines all allowed image categories across the platform. Each row specifies how uploaded images should be processed and how they appear in the admin UI.

| Column | Type | Default | Description |
|---|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT PK | — | |
| `code` | VARCHAR(100) UNIQUE NOT NULL | — | Machine-readable key used throughout the codebase (e.g. `product_thumb`, `ad_thumb`) |
| `name` | VARCHAR(255) NOT NULL | — | Human-readable display name |
| `description` | TEXT NULL | — | Optional description |
| `width` | INT UNSIGNED NOT NULL | — | Target width in pixels for resize/crop |
| `height` | INT UNSIGNED NOT NULL | — | Target height in pixels |
| `crop` | ENUM('fit','fill','cover') NOT NULL | `'cover'` | Resize strategy |
| `quality` | TINYINT UNSIGNED NOT NULL | `85` | JPEG/WebP quality (1–100) |
| `format` | ENUM('jpg','png','webp') NOT NULL | `'webp'` | Output file format |
| `is_thumbnail` | TINYINT(1) NOT NULL | `0` | `1` = this type produces thumbnail files |
| `icon` | VARCHAR(100) NOT NULL | `'fa-image'` | FontAwesome class for the admin UI badge (e.g. `fa-user-circle`) |
| `color` | VARCHAR(30) NOT NULL | `'#6b7280'` | CSS color for the admin UI badge (hex, named, rgb) |

**Migration to add `icon` and `color`:**
```sql
-- database/migrations/add_icon_color_to_image_types.sql
ALTER TABLE `image_types`
    ADD COLUMN `icon`  VARCHAR(100) NOT NULL DEFAULT 'fa-image' AFTER `is_thumbnail`,
    ADD COLUMN `color` VARCHAR(30)  NOT NULL DEFAULT '#6b7280'  AFTER `icon`;
```

---

### 2.2 Seed data — all 21 rows

| id | code | name | width | height | crop | quality | format | is_thumb | icon | color |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `category` | category | 600 | 600 | cover | 85 | webp | 0 | `fa-th-large` | `#8b5cf6` |
| 2 | `product` | product | 1000 | 1000 | cover | 90 | webp | 0 | `fa-box` | `#3b82f6` |
| 3 | `product_thumb` | product_thumb | 300 | 300 | cover | 85 | webp | 1 | `fa-image` | `#06b6d4` |
| 4 | `entity_logo` | entity_logo | 400 | 400 | fit | 85 | webp | 0 | `fa-building` | `#f59e0b` |
| 5 | `entity_cover` | entity_cover | 1200 | 400 | cover | 85 | webp | 0 | `fa-store` | `#f97316` |
| 6 | `entity_license` | entity_license | 1920 | 600 | cover | 85 | webp | 0 | `fa-id-card` | `#ef4444` |
| 7 | `avatar` | avatar | 256 | 256 | cover | 85 | webp | 1 | `fa-user-circle` | `#10b981` |
| 8 | `homepage_section` | homepage_section | 1200 | 600 | cover | 85 | webp | 0 | `fa-home` | `#14b8a6` |
| 9 | `banner` | banner | 1440 | 400 | cover | 85 | webp | 0 | `fa-flag` | `#6366f1` |
| 10 | `gallery` | gallery | 1600 | 1200 | fit | 85 | webp | 0 | `fa-images` | `#84cc16` |
| 11 | `job_categories` | job_categories | 800 | 600 | cover | 85 | webp | 0 | `fa-briefcase` | `#a855f7` |
| 12 | `brand` | brand | 600 | 600 | fit | 85 | webp | 0 | `fa-trademark` | `#ec4899` |
| 13 | `ad_homepage_banner` | Ad Homepage Banner | 1440 | 400 | cover | 90 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 14 | `ad_section_banner` | Ad Section Banner | 1200 | 300 | cover | 90 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 15 | `ad_square` | Ad Square | 400 | 400 | cover | 90 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 16 | `ad_store_banner` | Ad Store Banner | 1200 | 300 | cover | 90 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 17 | `ad_small` | Ad Small | 300 | 250 | cover | 85 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 18 | `ad_search_banner` | Ad Search Banner | 1200 | 200 | cover | 90 | webp | 0 | `fa-ad` | `#0ea5e9` |
| 19 | `ad_mobile_banner` | Ad Mobile Banner | 768 | 250 | cover | 90 | webp | 0 | `fa-mobile-alt` | `#0ea5e9` |
| 20 | `ad_thumb` | Ad Thumbnail | 300 | 150 | cover | 85 | webp | 1 | `fa-ad` | `#0ea5e9` |
| 21 | `tenant_logo` | Tenant Logo | 400 | 400 | fit | 85 | webp | 0 | `fa-building` | `#f59e0b` |

---

## 3. Backend File Structure

```
api/v1/
├── routes/
│   ├── image-types.php      ← Route: /api/image-types
│   └── images.php           ← Route: /api/images
│
└── models/images/
    ├── repositories/
    │   ├── PdoImageTypesRepository.php
    │   └── PdoImagesRepository.php
    ├── validators/
    │   ├── ImageTypesValidator.php
    │   └── ImagesValidator.php
    ├── services/
    │   ├── ImageTypesService.php
    │   └── ImagesService.php
    └── controllers/
        ├── ImageTypesController.php
        └── ImagesController.php
```

---

## 4. API Endpoints

### 4.1 `/api/image-types`

Manages the `image_types` table. **No tenant scoping** — image types are global/shared.

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?id=N` (optional) | List all types OR get single by ID |
| `GET` | `?code=ad_thumb` via `/api/image-types/resolve?code=ad_thumb` | Resolve type by `code` |
| `POST` | JSON body | Create new image type |
| `PUT` | JSON body with `id` | Update image type |
| `DELETE` | JSON body with `id` | Delete image type |

**Response (list):**
```json
{
  "success": true,
  "data": [
    {
      "id": 20,
      "code": "ad_thumb",
      "name": "Ad Thumbnail",
      "description": "صورة مصغرة للإعلان",
      "width": 300,
      "height": 150,
      "crop": "cover",
      "quality": 85,
      "format": "webp",
      "is_thumbnail": 1,
      "icon": "fa-ad",
      "color": "#0ea5e9"
    }
  ]
}
```

**Validation rules:**

| Field | Required | Rule |
|---|---|---|
| `code` | Yes (create) | lowercase letters, numbers, underscores only; unique |
| `name` | Yes (create) | max 50 chars |
| `description` | No | max 255 chars |
| `width` | Yes | zero or positive integer |
| `height` | Yes | positive integer |
| `crop` | No | `fit` \| `fill` \| `cover` |
| `quality` | No | 1–100 |
| `format` | No | `jpg` \| `png` \| `webp` |
| `is_thumbnail` | No | `0` or `1` |
| `icon` | No | max 100 chars (FontAwesome class) |
| `color` | No | max 30 chars; valid CSS color (hex, named, rgb, rgba) |

---

### 4.2 `/api/images`

Manages the `images` table (uploaded files). Requires `tenant_id`.

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?owner_id=&image_type_id=&tenant_id=&page=&limit=` | List images with filters |
| `GET` | `?id=N` | Get single image |
| `POST` (multipart) | `images[]`, `owner_id`, `image_type_id`, `tenant_id`, `user_id` | Upload one or more images |
| `PUT` | JSON body with `id` | Update image metadata |
| `DELETE` | JSON body with `id` | Delete image |

The upload handler reads the `image_type` record (by `image_type_id`) to determine `width`, `height`, `crop`, `quality`, `format` for server-side processing via the `upload.php` shared helper.

---

## 5. Admin Frontend — Media Studio

### 5.1 Fragment `admin/fragments/media_studio.php`

**Path:** `admin/fragments/media_studio.php`

**Purpose:** Renders the complete Media Studio HTML page. Works both as a standalone admin page and as an embeddable fragment loaded inside overlays from other modules (e.g. Ads, Products, Entities).

**Request detection:**
```php
$isFragment = (HTTP_X_REQUESTED_WITH == 'xmlhttprequest') || isset($_GET['embedded']) || isset($_POST['embedded']);
```

- **Fragment/embedded mode:** loads `admin/includes/admin_context.php`
- **Standalone mode:** loads `admin/includes/header.php` + `admin/includes/footer.php`

**URL parameters for auto-fill (embedding):**

| Param | Description |
|---|---|
| `owner_id` | Pre-selects the owner for uploaded images |
| `image_type_id` | Pre-selects and locks the image type |
| `tenant_id` | Pre-selects the tenant |
| `user_id` | Pre-selects the uploader user |
| `mode` | `manage` (default) or `select` (single-image picker) |
| `action` | Optional action hint |
| `limit` | Max images that can be selected (0 or >1 for multi-select) |
| `embedded` | Signals fragment mode |

**Permissions:**
- `can('manage_media')` or `is_super_admin()` → can create, edit, delete

**`window.MEDIA_STUDIO_CONFIG` object:**

| Key | Value | Notes |
|---|---|---|
| `apiUrl` | `'/api/images'` | All image CRUD calls |
| `translationsUrl` | `'/languages/Media_studio/{lang}.json'` | UI strings |
| `csrfToken` | Server-generated string | Sent in mutating requests |
| `tenantId` | Integer | Current tenant |
| `lang` | e.g. `'en'` | |
| `isSuperAdmin` | Boolean | |
| `autoFill` | `{owner_id, image_type_id, tenant_id, user_id}` | Pre-fill from URL params |
| `embedded` | Boolean | Whether loaded inside another module |
| `mode` | `'manage'` \| `'select'` | |
| `action` | String | |
| `selectionLimit` | Integer | 0 or >1 for multi-select |
| `permissions` | `{canCreate, canEdit, canDelete}` | |

**DB-driven CSS theming:**  
The fragment emits `<style id="db-theme-vars-media-studio">` with a `:root { … }` block generated by the `renderFragmentThemeVars($theme)` PHP function. This reads `color_settings`, `font_settings`, `design_settings`, `button_styles`, and `card_styles` from the `ADMIN_UI.theme` global (populated by `admin_context.php` from the database). No colors are hardcoded in PHP or JS.

**HTML structure:**

```
<div id="mediaStudioPage">
  │
  ├── Selection Bar (embedded select mode — fixed bar at top)
  │     Shows count + "Confirm Selection" button
  │
  ├── Studio Copy Bar (select-from-library mode)
  │     "Click an image below to use it" + Confirm/Cancel
  │
  ├── Page Header
  │     Title, Subtitle, "Add Image" button, "Select" button (select mode)
  │
  ├── Add Image Form (card, hidden by default)
  │     ├── Tab: Upload (drag-and-drop zone + file input)
  │     └── Tab: From Studio (enter studio copy mode to pick existing image)
  │
  ├── Edit Image Form (card, hidden by default)
  │     Full metadata form: owner_id, image_type (datalist from DB),
  │     tenant_id, user_id, filename, url, thumb_url, mime_type,
  │     size, visibility, is_main, sort_order
  │
  ├── Filter Bar
  │     Search input, Image Type filter (datalist from DB),
  │     Visibility filter, Apply/Reset buttons, Delete Selected
  │
  └── Gallery / Table
        Empty state, Error state, Loading spinner,
        <table> with columns: ☐ | Thumb | ID | Filename | Owner |
                               Image Type (icon+color badge) |
                               Visibility | Is Main | Sort | Date | Actions
```

---

### 5.2 JavaScript `admin/assets/js/pages/media_studio.js`

**Path:** `admin/assets/js/pages/media_studio.js` (1267 lines, IIFE, strict mode)

**Key state:**

| Variable | Description |
|---|---|
| `state.page` | Current pagination page |
| `state.perPage` | Items per page (default 25) |
| `state.filters` | Active filter values |
| `state.items` | Current page image records |
| `state.imageTypes` | All image type records from `/api/image-types` (includes `icon` and `color`) |
| `state.permissions` | `{canCreate, canEdit, canDelete}` |
| `state.selectedItems` | Selected image IDs |

**Main functions:**

| Function | Description |
|---|---|
| `loadTranslations()` | Fetches `MEDIA_STUDIO_CONFIG.translationsUrl` for UI strings |
| `t(key, placeholders)` | Returns translated string with placeholder substitution |
| `applyTranslations()` | Applies all `data-i18n` attribute values in `#mediaStudioPage` |
| `loadImageTypes()` | `GET /api/image-types` → populates `state.imageTypes` and both datalists |
| `populateDatalist(datalistId, data, valueKey, textKey)` | Fills an HTML `<datalist>` |
| `getIdFromDatalist(datalistId, displayValue)` | Reverse-lookup: display text → data-id |
| `setDisplayFromId(hiddenId, displayId, datalistId, idValue)` | Forward-lookup: id → display text |
| `loadData()` | `GET /api/images` with current `state.filters` and pagination |
| `renderTable()` | Renders `<tbody>` rows; each image type cell uses **`getImageTypeBadge()`** |
| `getImageTypeBadge(imageTypeId)` | Returns HTML badge `<span>` with inline `style="background:{color}"` and `<i class="fas {icon}">` — **values come exclusively from `state.imageTypes`** |
| `getImageTypeName(id)` | Returns plain text name (used for fallback / accessibility) |
| `showAddForm()` | Shows the Upload / From-Studio form card |
| `hideForm()` | Hides both form cards |
| `handleUploadFormSubmit(e)` | `POST /api/images` with `FormData` (multipart) |
| `handleFormSubmit(e)` | `PUT /api/images` to update metadata |
| `handleDelete(id)` | `DELETE /api/images` with confirmation |
| `handleMainToggle(e)` | `PUT /api/images` to toggle `is_main` |
| `handleSelectionConfirm()` | Fires `window.MediaStudio.selectedCallback(selectedItems)` |
| `handleFilterApply()` | Reads filter fields and calls `loadData()` |
| `handleFilterReset()` | Clears filters and reloads |
| `handleBulkDelete()` | Deletes all selected images |
| `init()` | Wires DOM elements, event listeners, loads types and data |
| `showNotification(msg, type)` | Toast in `#notificationsContainer` |
| `showEmpty()` / `showError()` / `showLoading()` | State UI helpers |
| `escapeHtml(text)` | XSS-safe escaping via `createTextNode` |

---

### 5.3 CSS `admin/assets/css/pages/media_studio.css`

Uses CSS custom properties from the DB-driven theme (`--primary-color`, `--background-primary`, etc.).

Key selectors:

| Selector | Purpose |
|---|---|
| `.notifications-container` | Fixed-position toast stack |
| `.notification-success/error/warning/info` | Toast variants |
| `.selection-bar` | Fixed top bar in select mode |
| `.studio-copy-bar` | Fixed top bar in studio copy mode |
| `.page-header` / `.page-header-actions` | Page title + action buttons |
| `.card` / `.form-card` | Card container for forms |
| `.upload-drop-zone` | Drag-and-drop upload area |
| `.image-table` / `tbody tr` | Gallery table |
| `.toggle-switch` / `.toggle-slider` | Is-Main toggle switch |
| `.table-actions` | Edit/Delete button group |
| `.empty-state` / `.error-state` | Empty/error placeholders |
| **`.image-type-badge`** | **Icon + color badge for image types (from DB)** |
| `.image-type-badge--unknown` | Fallback for unknown type IDs |

**Image type badge styles:**
```css
.image-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.55rem;
    border-radius: 0.4rem;
    font-size: 0.78rem;
    font-weight: 500;
    white-space: nowrap;
    /* background and color set inline via JS from DB values */
}
```

---

## 6. DB-Driven Theming (Colors & Fonts)

The `renderFragmentThemeVars()` PHP function in `media_studio.php` reads the global `$GLOBALS['ADMIN_UI']['theme']` array (populated by `admin_context.php` from the database) and emits CSS custom properties:

```
color_settings[]    → --{setting_key}: {color_value}
font_settings[]     → --{setting_key}-family / -size / -weight
design_settings[]   → --{setting_key}: {setting_value}
button_styles[]     → --btn-{slug}-bg / -color / -border / -radius
card_styles[]       → --card-{slug}-bg / -border / -radius / -shadow / -padding
```

Alias defaults are emitted if not already defined by any theme record:
- `--card-bg` → `background-secondary` or `#081127`
- `--input-bg` → `background-secondary` or `#0b1220`
- `--thead-bg` → `background-secondary` or `#061021`
- `--danger-color` → `#ef4444`
- `--success-color` → `#22c55e`

**CSS variables are NEVER hardcoded** in the media_studio page outside of these fallback values.

---

## 7. DB-Driven Image Type Badges (Icons & Colors)

Image type icons and badge colors are stored in the `image_types` table (`icon` VARCHAR, `color` VARCHAR). The JS function `getImageTypeBadge()` in `media_studio.js` uses these values directly from `state.imageTypes` (loaded from `/api/image-types`):

```javascript
function getImageTypeBadge(imageTypeId) {
    const type = state.imageTypes.find(t => t.id == imageTypeId);
    if (!type) {
        return `<span class="image-type-badge image-type-badge--unknown">Unknown</span>`;
    }
    const icon  = type.icon  || 'fa-image';   // from DB column `icon`
    const color = type.color || '#6b7280';    // from DB column `color`
    const name  = escapeHtml(type.name);
    return `<span class="image-type-badge" style="background:${escapeHtml(color)};color:#fff;" title="${name}">` +
           `<i class="fas ${escapeHtml(icon)}"></i> ${name}</span>`;
}
```

**To change an image type's badge appearance:** update the `icon` and/or `color` column in the `image_types` table — no code change required.

---

## 8. Embedding Media Studio in Other Modules

Media Studio can be embedded inside any admin module as an image picker overlay. Example (from the Ads module):

**PHP (in the fragment that wants to embed):**
```html
<!-- Overlay container -->
<div id="mediaStudioOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7);">
    <div id="mediaStudioFrame" style="..."></div>
</div>
```

**JavaScript (open the studio):**
```javascript
async function openMediaStudio(ownerId, imageTypeId, onSelect) {
    const url = `/admin/fragments/media_studio.php?embedded=1&mode=select` +
                `&owner_id=${ownerId}&image_type_id=${imageTypeId}&tenant_id=${CFG.tenantId}`;

    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const html = await response.text();

    document.getElementById('mediaStudioFrame').innerHTML = html;
    document.getElementById('mediaStudioOverlay').style.display = 'block';

    // Callback fired when user confirms selection
    window.MediaStudio = { selectedCallback: onSelect };
    if (window.MediaStudioModule) window.MediaStudioModule.init();
}
```

**Callback payload** (single image):
```json
{
  "id": 42,
  "url": "/uploads/images/img_xxx.webp",
  "thumb_url": "/uploads/images/thumb_xxx.webp",
  "filename": "img_xxx.webp",
  "image_type_id": 20,
  "owner_id": 7,
  "tenant_id": 1
}
```

---

## 9. Data Flow & Architecture

```
Browser
  │
  ├── GET /admin/fragments/media_studio.php[?embedded=1&owner_id=X&image_type_id=N]
  │         │
  │         ├── admin_context.php (auth, theme, tenant)
  │         ├── renderFragmentThemeVars($theme)  ← emits DB-driven CSS vars
  │         └── outputs HTML + window.MEDIA_STUDIO_CONFIG{...}
  │
  └── MediaStudio JS init()
        │
        ├── loadTranslations()
        │     └── GET /languages/Media_studio/{lang}.json
        │
        ├── loadImageTypes()
        │     └── GET /api/image-types
        │           └── PdoImageTypesRepository.all()
        │                 └── SELECT id, code, name, ..., icon, color FROM image_types
        │           → state.imageTypes = [{id, icon, color, ...}, ...]
        │           → populate datalists (Upload form + Filter bar)
        │
        └── loadData()
              └── GET /api/images?tenant_id=X&page=1&...filters...
                    └── PdoImagesRepository.all(filters)
                    → state.items = [{id, url, thumb_url, image_type_id, ...}, ...]
                    → renderTable()
                          └── for each item:
                                imageTypeBadge = getImageTypeBadge(item.image_type_id)
                                ← looks up state.imageTypes[x].icon + .color
                                ← renders: <span style="background:{color}"><i class="fas {icon}"> {name}</span>
```

---

## 10. Security Notes

| Concern | Implementation |
|---|---|
| **XSS** | All dynamic values escaped with `escapeHtml()` (createTextNode) before DOM insertion. Badge `icon` and `color` values are also escaped. |
| **CSRF** | `csrfToken` injected server-side in `MEDIA_STUDIO_CONFIG`, sent in all POST/PUT/DELETE. |
| **SQL Injection** | All PDO queries use named prepared statements. `ORDER BY` columns validated against a whitelist. |
| **Path traversal** | Upload helper sanitises filenames before writing to disk. |
| **Permissions** | Fragment checks `can('manage_media')` or `is_super_admin()` for all write operations. Read-only access is available without permissions (gallery browse). |
| **XSS via DB** | `icon` value (e.g. `fa-image`) is escaped before insertion into HTML. `color` value is validated server-side by regex (`ImageTypesValidator`) to only allow valid CSS color syntax. |
