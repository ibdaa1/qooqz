# Ads Module — Complete Technical Documentation

## Table of Contents

1. [Module Overview](#1-module-overview)
2. [Database Schema](#2-database-schema)
3. [Backend File Structure](#3-backend-file-structure)
4. [API Endpoints](#4-api-endpoints)
   - [Ad Campaigns `/api/ad_campaigns`](#41-ad-campaigns-apiad_campaigns)
   - [Ad Units `/api/ads`](#42-ad-units-apiads)
   - [Ad Translations `/api/ad_translations`](#43-ad-translations-apiad_translations)
   - [Ad Placements `/api/ad_placements`](#44-ad-placements-apiad_placements)
   - [Ad Placement Items `/api/ad_placement_items`](#45-ad-placement-items-apiad_placement_items)
   - [Ad Payments `/api/ad_payments`](#46-ad-payments-apiad_payments)
5. [Admin Frontend](#5-admin-frontend)
   - [Fragment `admin/fragments/ads.php`](#51-fragment-adminfragmentsadsphp)
   - [JavaScript `admin/assets/js/pages/ads.js`](#52-javascript-adminassetsjspagesadsjs)
   - [CSS `admin/assets/css/pages/ads.css`](#53-css-adminassetscsspagesadscss)
   - [Language Files `languages/Ads/`](#54-language-files-languagesads)
6. [Data Flow & Architecture](#6-data-flow--architecture)
7. [Tenant Isolation & Security](#7-tenant-isolation--security)
8. [Important Technical Notes](#8-important-technical-notes)

---

## 1. Module Overview

The **Ads Module** is a full advertising management system embedded in the admin panel. Each tenant can:

| Feature | Description |
|---|---|
| **Ad Campaigns** | Create campaigns with budget, currency, pricing model, schedule, and status |
| **Ad Units** | Create individual ad units that belong to a campaign; each has a target (URL or entity), status, view/click counters |
| **Translations** | Add multilingual title + description per ad unit (any language code) |
| **Ad Images** | Upload 8 sizes of images per ad via the Media Studio overlay |
| **Ad Placements** | Define named ad slots on pages (homepage banner, sidebar, etc.) with dimensions and max-ad limits |
| **Placement Items** | Assign ad units to placements with priority, weight, and optional date range |
| **Ad Payments** | Track payments linked to campaigns with currency, amount, and status |

**All data is scoped per tenant** — every query filters by `tenant_id` directly on the table or via a JOIN through `ad_campaigns`.

---

## 2. Database Schema

### 2.1 `ad_campaigns`

Stores ad campaigns; the root entity that ties everything to a tenant.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `tenant_id` | INT UNSIGNED NOT NULL | FK → `tenants.id` |
| `entity_id` | INT UNSIGNED NULL | Optional FK → `entities.id` |
| `name` | VARCHAR(255) NOT NULL | Campaign display name |
| `budget` | DECIMAL(18,4) DEFAULT 0 | Total budget |
| `currency_id` | INT UNSIGNED NULL | FK → `currencies.id` |
| `pricing_model` | ENUM('fixed','cpm','cpc') DEFAULT 'fixed' | |
| `start_date` | DATE NULL | |
| `end_date` | DATE NULL | |
| `status` | ENUM('draft','active','paused','completed') DEFAULT 'draft' | |
| `created_by` | INT UNSIGNED NULL | FK → `users.id` |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Indexes:** PRIMARY (`id`), `tenant_id`, `status`, `start_date`, `end_date`.

**JOIN enrichment in API response:**  
`currencies` → `currency_code`, `currency_name`, `currency_symbol`, `currency_symbol_position`, `currency_decimal_places`  
`entities` → `entity_store_name`  
`users` → `created_by_name`

---

### 2.2 `ads`

Individual ad units belonging to a campaign.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `campaign_id` | BIGINT UNSIGNED NOT NULL | FK → `ad_campaigns.id` |
| `target_type` | ENUM('url','entity') DEFAULT 'url' | Where the ad points |
| `target_value` | VARCHAR(500) NULL | URL string or entity identifier |
| `status` | ENUM('active','paused','rejected') DEFAULT 'active' | |
| `views_count` | BIGINT UNSIGNED DEFAULT 0 | Impression counter |
| `clicks_count` | BIGINT UNSIGNED DEFAULT 0 | Click counter |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Tenant isolation:** Achieved by `INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id WHERE ac.tenant_id = :tenant_id`.

**JOIN enrichment in API response:**  
`ad_campaigns` → `campaign_name`, `campaign_status`, `campaign_tenant_id`

---

### 2.3 `ad_translations`

Multilingual title + description for each ad unit.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `ad_id` | BIGINT UNSIGNED NOT NULL | FK → `ads.id` |
| `language_code` | VARCHAR(8) NOT NULL | ISO code e.g. `en`, `ar`, `fr` |
| `title` | VARCHAR(255) NULL | Ad headline |
| `description` | TEXT NULL | Ad body text |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Note:** `ads` table has **no** `title` column. Title always comes from `ad_translations` for the requested language.

**Tenant isolation:** Via `INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id WHERE ac.tenant_id = :tenant_id`.

---

### 2.4 `ad_placements`

Named ad slots/positions available on platform pages.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `tenant_id` | INT UNSIGNED NOT NULL | FK → `tenants.id` |
| `code` | VARCHAR(100) UNIQUE NULL | Machine-readable identifier |
| `name` | VARCHAR(255) NOT NULL | Human-readable slot name |
| `description` | TEXT NULL | |
| `placement_key` | VARCHAR(100) NOT NULL | Template key used in front-end rendering (e.g. `homepage_banner`) |
| `page` | VARCHAR(100) NULL | Page where the slot appears |
| `width` | INT UNSIGNED NULL | Expected ad width (px) |
| `height` | INT UNSIGNED NULL | Expected ad height (px) |
| `max_ads` | INT UNSIGNED DEFAULT 1 | Maximum concurrent ads in this slot |
| `status` | ENUM('active','inactive','draft') DEFAULT 'active' | |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

> **No `updated_at` column** exists on this table.

**Indexes:** `tenant_id`, UNIQUE(`code`), `placement_key`, `status`.

---

### 2.5 `ad_placement_items`

Join table that assigns an ad unit to a placement slot with scheduling metadata.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `placement_id` | BIGINT UNSIGNED NOT NULL | FK → `ad_placements.id` |
| `ad_id` | BIGINT UNSIGNED NOT NULL | FK → `ads.id` |
| `priority` | INT UNSIGNED DEFAULT 1 | Higher = shown first |
| `weight` | INT UNSIGNED DEFAULT 1 | Weighted random selection |
| `start_date` | DATE NULL | When ad becomes active in this slot |
| `end_date` | DATE NULL | When ad expires from this slot |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Tenant isolation:** Via `INNER JOIN ad_placements ap ON api.placement_id = ap.id WHERE ap.tenant_id = :tenant_id`.

**JOIN enrichment in API response:**  
`ad_translations` (language = `en`) → `ad_title` (via `COALESCE(atr.title, '') AS ad_title`)

---

### 2.6 `ad_payments`

Payment records linked to campaigns.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `campaign_id` | BIGINT UNSIGNED NOT NULL | FK → `ad_campaigns.id` |
| `currency_id` | INT UNSIGNED NULL | FK → `currencies.id` |
| `amount` | DECIMAL(18,4) DEFAULT 0 | |
| `status` | ENUM('pending','paid','failed') DEFAULT 'pending' | |
| `paid_at` | TIMESTAMP NULL | |
| `created_at` | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |

**Tenant isolation:** Via `INNER JOIN ad_campaigns ac ON ap.campaign_id = ac.id WHERE ac.tenant_id = :tenant_id`.

**JOIN enrichment in API response:**  
`currencies` → `currency_code`, `currency_name`, `currency_symbol`, `currency_symbol_position`, `currency_decimal_places`  
`ad_campaigns` → `campaign_name`, `campaign_tenant_id`

---

## 3. Backend File Structure

```
api/v1/
├── routes/
│   ├── ads.php                        ← Route: /api/ads
│   ├── ad_campaigns.php               ← Route: /api/ad_campaigns
│   ├── ad_translations.php            ← Route: /api/ad_translations
│   ├── ad_placements.php              ← Route: /api/ad_placements
│   ├── ad_placement_items.php         ← Route: /api/ad_placement_items
│   └── ad_payments.php                ← Route: /api/ad_payments
│
└── models/ads/
    ├── Contracts/
    │   ├── AdsRepositoryInterface.php
    │   ├── AdCampaignsRepositoryInterface.php
    │   ├── AdTranslationsRepositoryInterface.php
    │   ├── AdPlacementsRepositoryInterface.php
    │   ├── AdPlacementItemsRepositoryInterface.php
    │   └── AdPaymentsRepositoryInterface.php
    │
    ├── repositories/
    │   ├── PdoAdsRepository.php
    │   ├── PdoAdCampaignsRepository.php
    │   ├── PdoAdTranslationsRepository.php
    │   ├── PdoAdPlacementsRepository.php
    │   ├── PdoAdPlacementItemsRepository.php
    │   └── PdoAdPaymentsRepository.php
    │
    ├── validators/
    │   ├── AdsValidator.php
    │   ├── AdCampaignsValidator.php
    │   ├── AdTranslationsValidator.php
    │   ├── AdPlacementsValidator.php
    │   ├── AdPlacementItemsValidator.php
    │   └── AdPaymentsValidator.php
    │
    ├── services/
    │   ├── AdsService.php
    │   ├── AdCampaignsService.php
    │   ├── AdTranslationsService.php
    │   ├── AdPlacementsService.php
    │   ├── AdPlacementItemsService.php
    │   └── AdPaymentsService.php
    │
    └── controllers/
        ├── AdsController.php
        ├── AdCampaignsController.php
        ├── AdTranslationsController.php
        ├── AdPlacementsController.php
        ├── AdPlacementItemsController.php
        └── AdPaymentsController.php
```

Each entity follows the same **4-layer** pattern:

```
Route → Controller → Service → Repository (PDO)
```

Validation is done in the **Service** layer using the corresponding Validator class. The **Controller** only routes calls; it contains no business logic.

---

## 4. API Endpoints

All endpoints:
- Require `tenant_id` as a query param (`?tenant_id=X`) or from `$_SESSION['tenant_id']`
- Return `Content-Type: application/json`
- Return a consistent envelope: `{"success": true, "data": {...}}` on success, `{"success": false, "message": "..."}` on error

### 4.1 Ad Campaigns `/api/ad_campaigns`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) | List all campaigns OR get single by `id` |
| `GET` | `?tenant_id=` `&status=` `&pricing_model=` `&currency_id=` `&entity_id=` `&search=` `&page=` `&limit=` `&order_by=` `&order_dir=` | Filtered / paginated list |
| `POST` | JSON body | Create new campaign |
| `PUT` | JSON body with `id` | Update campaign |
| `DELETE` | JSON body with `id` | Delete campaign |

**Response (list):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "tenant_id": 2,
        "name": "Summer Sale",
        "budget": "5000.0000",
        "currency_id": 1,
        "pricing_model": "cpm",
        "start_date": "2024-06-01",
        "end_date": "2024-06-30",
        "status": "active",
        "created_at": "2024-05-15 10:00:00",
        "currency_code": "USD",
        "currency_name": "US Dollar",
        "currency_symbol": "$",
        "entity_store_name": null,
        "created_by_name": "admin"
      }
    ],
    "meta": { "total": 1, "page": 1, "per_page": 20, "total_pages": 1 }
  }
}
```

**Validation rules (create / update):**

| Field | Create | Update | Rule |
|---|---|---|---|
| `name` | Required | Optional | max 255 chars |
| `currency_id` | Required | Optional | positive integer |
| `entity_id` | Optional | Optional | positive integer |
| `budget` | Optional | Optional | non-negative number |
| `status` | Optional | Optional | `draft` \| `active` \| `paused` \| `completed` |
| `pricing_model` | Optional | Optional | `fixed` \| `cpm` \| `cpc` |
| `start_date` | Optional | Optional | ISO date |
| `end_date` | Optional | Optional | ISO date |

---

### 4.2 Ad Units `/api/ads`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) | List all ads OR get single |
| `GET` | `?tenant_id=` `&status=` `&target_type=` `&campaign_id=` `&search=` `&page=` `&limit=` `&order_by=` `&order_dir=` | Filtered list |
| `POST` | JSON body | Create ad unit |
| `PUT` | JSON body with `id` | Update ad unit |
| `DELETE` | JSON body/query with `id` | Delete ad unit |

**Allowed `order_by` values:** `id`, `campaign_id`, `target_type`, `status`, `views_count`, `clicks_count`, `created_at`

**Validation rules:**

| Field | Create | Update | Rule |
|---|---|---|---|
| `campaign_id` | Required | Optional | positive integer |
| `target_type` | Optional | Optional | `url` \| `entity` |
| `target_value` | Optional | Optional | max 500 chars |
| `status` | Optional | Optional | `active` \| `paused` \| `rejected` |

**Security note:** On INSERT, the service verifies that the provided `campaign_id` belongs to `tenant_id` before inserting. On UPDATE/DELETE, the repository uses `INNER JOIN ad_campaigns` to enforce ownership.

---

### 4.3 Ad Translations `/api/ad_translations`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) `&ad_id=` `&language_code=` `&search=` `&page=` `&limit=` | List translations (filtered) |
| `POST` | JSON body | Create translation |
| `PUT` | JSON body with `id` | Update translation |
| `DELETE` | JSON body with `id` | Delete translation |

**Validation rules:**

| Field | Create | Update | Rule |
|---|---|---|---|
| `ad_id` | Required | Optional | positive integer |
| `language_code` | Required | Optional | max 8 chars |
| `title` | Optional | Optional | max 255 chars |
| `description` | Optional | Optional | text |

**Important:** The `ads.php` admin fragment auto-saves an `en` translation whenever a new ad unit is created (via the "English Title" and "English Description" fields in the Basic tab of the Ad Unit modal). This guarantees every ad has at least an English title used as the `ad_title` fallback in `ad_placement_items` queries.

---

### 4.4 Ad Placements `/api/ad_placements`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) `&status=` `&search=` `&page=` `&limit=` `&order_by=` `&order_dir=` | List OR get single |
| `POST` | JSON body | Create placement |
| `PUT` | JSON body with `id` | Update placement |
| `DELETE` | JSON body with `id` | Delete placement |

**Default limit:** 50 per page.

**Allowed `order_by` values:** `id`, `name`, `placement_key`, `status`, `created_at`, `updated_at`

**Validation rules:**

| Field | Create | Update | Rule |
|---|---|---|---|
| `name` | Required | Optional | max 255 chars |
| `placement_key` | Required | Optional | max 100 chars; `[a-z0-9_-]` only |
| `code` | Optional | Optional | max 100 chars; `[a-z0-9_-]` only |
| `page` | Optional | Optional | max 100 chars |
| `width` | Optional | Optional | positive integer |
| `height` | Optional | Optional | positive integer |
| `max_ads` | Optional | Optional | positive integer |
| `status` | Optional | Optional | `active` \| `inactive` \| `draft` |

---

### 4.5 Ad Placement Items `/api/ad_placement_items`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) `&placement_id=` `&ad_id=` `&page=` `&limit=` `&order_by=` `&order_dir=` | List items OR get single |
| `POST` | JSON body | Assign ad to placement |
| `PUT` | JSON body with `id` | Update assignment |
| `DELETE` | JSON body with `id` | Remove assignment |

**Response includes `ad_title`** (from `ad_translations` for `language_code = 'en'` via `COALESCE`).

**Validation rules:**

| Field | Create | Update | Rule |
|---|---|---|---|
| `placement_id` | Required | Optional | positive integer |
| `ad_id` | Required | Optional | positive integer |
| `priority` | Optional | Optional | positive integer |
| `weight` | Optional | Optional | positive integer |
| `start_date` | Optional | Optional | ISO date |
| `end_date` | Optional | Optional | ISO date |

---

### 4.6 Ad Payments `/api/ad_payments`

| Method | Params / Body | Description |
|---|---|---|
| `GET` | `?tenant_id=` `&id=` (optional) `&campaign_id=` `&status=` `&currency_id=` `&page=` `&limit=` `&order_by=` `&order_dir=` | List payments OR get single |
| `POST` | JSON body | Record payment |
| `PUT` | JSON body with `id` | Update payment |
| `DELETE` | JSON body with `id` | Delete payment |

**Allowed `order_by` values:** `id`, `amount`, `status`, `paid_at`, `created_at`

**Validation rules:**

| Field | Create | Update | Rule |
|---|---|---|---|
| `campaign_id` | Required | Optional | positive integer |
| `currency_id` | Required | Optional | positive integer |
| `amount` | Optional | Optional | non-negative number |
| `status` | Optional | Optional | `pending` \| `paid` \| `failed` |

---

## 5. Admin Frontend

### 5.1 Fragment `admin/fragments/ads.php`

**Path:** `admin/fragments/ads.php`

**Purpose:** Renders the complete Ads Management page HTML. Injects all server-side data into `window.ADS_CONFIG` for the JavaScript layer.

**Request detection:**
```php
$isAjax     = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;
```

- **Fragment mode:** loads `admin/includes/admin_context.php` (no full HTML headers).
- **Standalone mode:** loads `admin/includes/header.php` and `admin/includes/footer.php`.

**Authentication & permissions:**
- Calls `is_admin_logged_in()` — redirects to `/admin/login.php` if not authenticated.
- Calls `can('manage_ads')` or `is_super_admin()` — returns 403 if denied.
- Exposes `$canCreate`, `$canEdit`, `$canDelete` booleans to both PHP template and `window.ADS_CONFIG`.

**Language / translations:**
- Loads `languages/Ads/{lang}.json` (falls back to `en.json`).
- Translates strings server-side via `_adst('key', 'fallback')` helper.
- Also passes `strings` object via `window.ADS_CONFIG.strings` for the JavaScript layer.

**Tabs structure (HTML):**

```
┌─────────────────────────────────────────────────┐
│  [ Campaigns ] [ Ad Units ] [ Placements ]       │
├─────────────────────────────────────────────────┤
│  Tab: Campaigns                                  │
│    Filter bar (search, status, pricing model)   │
│    Campaigns table + pagination                  │
│    Campaign modal (Add/Edit)                     │
├─────────────────────────────────────────────────┤
│  Tab: Ad Units                                   │
│    Filter bar (search, status, campaign, type)  │
│    Ads table with image thumbnail column         │
│    Ad Unit modal with 3 inner tabs:              │
│      ● Basic (title EN, description EN, fields) │
│      ● Translations (add/remove per language)   │
│      ● Images (8 ad image types 13–20)          │
├─────────────────────────────────────────────────┤
│  Tab: Placements                                 │
│    Placements table + pagination                 │
│    Placement modal (Add/Edit)                   │
│    Placement Items panel (per placement)         │
└─────────────────────────────────────────────────┘
```

**`window.ADS_CONFIG` object:**

| Key | Value | Notes |
|---|---|---|
| `apiBase` | `'/api'` | Base for all API calls |
| `csrfToken` | Server-generated CSRF string | Sent in all POST/PUT/DELETE |
| `tenantId` | Integer | Current tenant |
| `lang` | e.g. `'en'` | UI language |
| `dir` | `'ltr'` \| `'rtl'` | Text direction |
| `strings` | Object | All UI strings from language JSON |
| `canCreate` | Boolean | Permission flag |
| `canEdit` | Boolean | Permission flag |
| `canDelete` | Boolean | Permission flag |
| `imagesApi` | `'/api/images'` | For Media Studio integration |
| `translationsApi` | `'/api/ad_translations'` | |
| `placementsApi` | `'/api/ad_placements'` | |
| `placementItemsApi` | `'/api/ad_placement_items'` | |
| `adImageTypeId` | `20` | `image_types.id` for `ad_thumb` |

---

### 5.2 JavaScript `admin/assets/js/pages/ads.js`

**Path:** `admin/assets/js/pages/ads.js` (1664 lines, IIFE, strict mode)

**Public API:** `window.Ads.init()` — called by the page or fragment loader.

**Key state variables:**

| Variable | Description |
|---|---|
| `CFG` | Reference to `window.ADS_CONFIG` |
| `CSRF`, `STRINGS`, `CAN_CREATE`, `CAN_EDIT`, `CAN_DELETE` | Unpacked from `CFG` |
| `campaignsPage`, `campaignsFilters`, `campaignCache`, `currencyCache` | Campaigns tab state |
| `adsPage`, `adsFilters`, `adSelectedImages` | Ad Units tab state |
| `placementsPage`, `placementsFilters`, `currentPlacementId`, `placementItemsPage` | Placements tab state |
| `activeTab` | Currently visible tab: `'campaigns'` \| `'ads'` \| `'placements'` |

**Main functions:**

| Function | Description |
|---|---|
| `reloadConfig()` | Re-reads `window.ADS_CONFIG` (called on init and re-render) |
| `t(key, fallback)` | Traverses `STRINGS` dot-notation to return translated string |
| `esc(str)` | XSS-safe HTML escaping via `document.createTextNode` |
| `showNotification(msg, type)` | Creates toast notification in `#adsNotifications` div |
| `loadCampaigns()` | `GET /api/ad_campaigns?tenant_id=…` with filters/pagination |
| `renderCampaignsTable(items, meta)` | Renders campaigns `<tbody>` and pagination |
| `saveCampaign(data)` | POST or PUT to `/api/ad_campaigns` |
| `deleteCampaign(id)` | DELETE to `/api/ad_campaigns` |
| `loadAds()` | `GET /api/ads?tenant_id=…` with filters |
| `renderAdsTable(items, meta)` | Renders ads `<tbody>` with thumbnail |
| `openAdModal(id?)` | Opens Add/Edit modal; if `id` provided, pre-fills fields and loads translations + images |
| `saveAd(data)` | POST or PUT to `/api/ads`; on create, also auto-saves EN translation |
| `loadAllAdImages(adId)` | Loads images for all 8 image types (IDs 13–20) grouped by `name` field |
| `openMediaStudio(adId, imageTypeId, callback)` | Embeds `admin/fragments/media_studio.php` in an overlay for image upload |
| `loadTranslations(adId)` | `GET /api/ad_translations?ad_id=…&tenant_id=…` |
| `saveTranslation(adId, lang, title, desc)` | POST or PUT translation |
| `deleteTranslation(id)` | DELETE translation |
| `loadPlacements()` | `GET /api/ad_placements?tenant_id=…` |
| `renderPlacementsTable(items, meta)` | Renders placements `<tbody>` |
| `savePlacement(data)` | POST or PUT placement |
| `loadPlacementItems(placementId)` | `GET /api/ad_placement_items?placement_id=…&tenant_id=…` |
| `savePlacementItem(data)` | POST or PUT placement item |

**Ad Unit Modal — 3 inner tabs:**

1. **Basic tab:** `adEnTitle` and `adEnDescription` are mandatory (saved as `language_code = 'en'` translation on every save). `campaign_id`, `target_type`, `target_value`, `status`.
2. **Translations tab:** Table of existing translations; inline add/edit/delete per language code.
3. **Images tab:** Grid of 8 ad image types (IDs 13–20) each with current image thumbnail and "Upload" button. Clicking upload opens the Media Studio overlay scoped to `image_type_id=<N>` and `owner_id=<adId>`.

---

### 5.3 CSS `admin/assets/css/pages/ads.css`

**Path:** `admin/assets/css/pages/ads.css`

Styles the entire Ads Management page. Uses CSS custom properties defined by the DB-driven theme system (`--primary-color`, `--background-primary`, `--text-primary`, etc.) with fallback hardcoded values.

Key classes:

| Selector | Purpose |
|---|---|
| `.ads-tabs` / `.ads-tab-btn` | Top-level tab bar (Campaigns / Ad Units / Placements) |
| `.ads-table-container` | Scrollable table wrapper |
| `.ads-table` | Data table base styles |
| `.badge-status-*` | Status badges (active, paused, rejected, draft, completed) |
| `.ads-modal` | Full-screen modal overlay |
| `.ads-modal-inner` | Modal dialog box |
| `.modal-tabs` / `.modal-tab-btn` | Inner modal tabs (Basic / Translations / Images) |
| `.ad-images-grid` | Grid of ad image slots |
| `.ads-toast` / `.ads-toast-success` / `.ads-toast-error` | Toast notifications |
| `.ads-notifications` | Fixed-position notification container |

---

### 5.4 Language Files `languages/Ads/`

**Paths:** `languages/Ads/en.json`, `languages/Ads/ar.json` (and other locales)

**Structure:**
```json
{
  "strings": {
    "title": "Ads Management",
    "subtitle": "Manage ad campaigns and advertising units",
    "tab_campaigns": "Campaigns",
    "tab_ads": "Ad Units",
    "tab_placements": "Placements",
    "add_ad": "Add Ad Unit",
    "edit_ad": "Edit Ad Unit",
    "add_campaign": "Add Campaign",
    "edit_campaign": "Edit Campaign",
    "filter": { ... },
    "status": { "active": "Active", "paused": "Paused", ... },
    "pricing_model": { "fixed": "Fixed", "cpm": "CPM", "cpc": "CPC" },
    "target_type": { "url": "URL", "entity": "Entity" },
    "table": { "id": "ID", "campaign": "Campaign", ... },
    "campaigns_table": { "id": "ID", "name": "Name", ... },
    "placements_table": { ... },
    "placement_items_table": { ... },
    "modal": { ... },
    "notifications": { ... }
  }
}
```

The PHP fragment reads the JSON server-side and passes it in `window.ADS_CONFIG.strings`. The JS `t()` helper traverses dot-notation keys (e.g. `t('filter.search_placeholder')`).

---

## 6. Data Flow & Architecture

```
Browser
  │
  ├── GET  /admin/fragments/ads.php
  │         │
  │         ├── admin_context.php  (auth, user, tenant, theme)
  │         ├── Languages/Ads/{lang}.json  (translations)
  │         └── outputs HTML + window.ADS_CONFIG{...}
  │
  └── window.Ads.init()
        │
        ├── loadCampaigns() → GET /api/ad_campaigns?tenant_id=X
        │     └── PdoAdCampaignsRepository.all(tenantId)
        │           └── SQL: SELECT ac.*, c.*, e.*, u.* FROM ad_campaigns WHERE tenant_id = X
        │
        ├── (on tab switch) loadAds() → GET /api/ads?tenant_id=X
        │     └── PdoAdsRepository.all(tenantId)
        │           └── SQL: SELECT a.*, ac.* FROM ads a
        │                     INNER JOIN ad_campaigns ac ON a.campaign_id = ac.id
        │                     WHERE ac.tenant_id = X
        │
        ├── (on ad open) loadTranslations() → GET /api/ad_translations?ad_id=N&tenant_id=X
        │     └── PdoAdTranslationsRepository.all(tenantId, filters:{ad_id:N})
        │
        ├── (on ad open) loadAllAdImages() → GET /api/images for types 13..20
        │     └── PdoImagesRepository.all(filters:{owner_id, image_type_id})
        │
        ├── (on tab switch) loadPlacements() → GET /api/ad_placements?tenant_id=X
        │     └── PdoAdPlacementsRepository.all(tenantId)
        │
        └── (on placement click) loadPlacementItems(placementId)
              → GET /api/ad_placement_items?placement_id=N&tenant_id=X
              └── PdoAdPlacementItemsRepository.all(tenantId, filters:{placement_id:N})
                    └── SQL: SELECT api.*, COALESCE(atr.title,'') AS ad_title
                              FROM ad_placement_items api
                              INNER JOIN ad_placements ap ON api.placement_id = ap.id
                              LEFT JOIN ads a ON api.ad_id = a.id
                              LEFT JOIN ad_translations atr ON a.id = atr.ad_id AND atr.language_code = 'en'
                              WHERE ap.tenant_id = X
```

---

## 7. Tenant Isolation & Security

### Scoping strategy per entity

| Entity | How tenant is enforced |
|---|---|
| `ad_campaigns` | Direct: `WHERE tenant_id = :tenant_id` |
| `ads` | Indirect: `INNER JOIN ad_campaigns ac … WHERE ac.tenant_id = :tenant_id` |
| `ad_translations` | Indirect: `INNER JOIN ad_campaigns ac ON atr.ad_id = ac.id WHERE ac.tenant_id = :tenant_id` |
| `ad_placements` | Direct: `WHERE tenant_id = :tenant_id` |
| `ad_placement_items` | Indirect: `INNER JOIN ad_placements ap … WHERE ap.tenant_id = :tenant_id` |
| `ad_payments` | Indirect: `INNER JOIN ad_campaigns ac … WHERE ac.tenant_id = :tenant_id` |

### CSRF Protection
All mutating requests (POST/PUT/DELETE) send `csrf_token` in the request body. The `CSRF` value is server-generated in `ads.php` and injected into `window.ADS_CONFIG.csrfToken`.

### Input Validation
All user input is validated in the **Service** layer before any SQL is executed. Validators use whitelisted status/enum values and explicit type checks.

### SQL Injection Prevention
All PDO queries use named **prepared statements** with bound parameters. Dynamic `ORDER BY` columns are validated against a `ALLOWED_ORDER_BY` whitelist array in each repository.

### XSS Prevention
The JavaScript layer escapes all user-controlled values with the `esc()` helper (which uses `document.createTextNode`) before inserting into the DOM via template literals.

---

## 8. Important Technical Notes

1. **`ads` table has no `title` column.** Ad titles always come from `ad_translations` for the desired `language_code`. The API response for placement items uses `COALESCE(atr.title, '') AS ad_title` joining on `language_code = 'en'`.

2. **`ad_placements` has no `updated_at` column.** Do not include it in INSERT/UPDATE or SELECT queries. Only `created_at` exists.

3. **EN translation is mandatory in the UI.** The Ad Unit modal's Basic tab requires `adEnTitle` and `adEnDescription`. On save, the JS automatically calls `POST /api/ad_translations` with `language_code = 'en'` (or updates an existing EN translation).

4. **Image type ID 20 = `ad_thumb`.** This is the main thumbnail for an ad unit (shown in the Ads table). Image type IDs 13–20 are all ad-related types.

5. **Placement Items tenant scope changed.** The correct JOIN path is through `ad_placements` (not through `ads → ad_campaigns`). An earlier version had the wrong join which caused items to be invisible. Current code joins: `ad_placement_items → ad_placements (via placement_id) → WHERE ap.tenant_id = :tenant_id`.

6. **Ad Unit creation flow (2 API calls):**
   - `POST /api/ads` → returns `id`
   - `POST /api/ad_translations` with `ad_id = <id>`, `language_code = 'en'`, `title`, `description`

7. **Media Studio is embedded** inside an overlay `<div>` (not in an `<iframe>`). The fragment is fetched via AJAX with `?embedded=1&owner_id=<adId>&image_type_id=<typeId>`. On image selection the `window.MediaStudio.selectedCallback` is called with the image data.

8. **Pagination defaults:**
   - `/api/ad_campaigns`: 20 per page
   - `/api/ads`: 20 per page
   - `/api/ad_placements`: 50 per page
   - `/api/ad_placement_items`: 50 per page
   - `/api/ad_payments`: 20 per page
