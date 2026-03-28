# Notification System Documentation

> Complete reference for the QOOQZ notification system.
> Last updated: 2026-03-28

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [File Map](#file-map)
4. [Database Tables](#database-tables)
5. [Configuration](#configuration)
6. [Backend API](#backend-api)
7. [Notification Class Methods](#notification-class-methods)
8. [FCM Push Notifications](#fcm-push-notifications)
9. [Frontend Integration](#frontend-integration)
10. [Admin Panel](#admin-panel)
11. [Translations](#translations)
12. [Troubleshooting](#troubleshooting)

---

## Overview

The QOOQZ notification system supports **4 delivery channels**:

| Channel    | Description                        | Config Flag    |
|------------|------------------------------------|----------------|
| `database` | Stores in `notifications` table    | Always on      |
| `push`     | Firebase Cloud Messaging (FCM)     | FCM_PROJECT_ID |
| `email`    | Email via SMTP                     | MAIL_ENABLED   |
| `sms`      | SMS via provider                   | SMS_ENABLED    |

Notifications can be sent to **3 recipient types**: `user`, `entity`, `tenant`.

---

## Architecture

```
Admin Panel (notification.php)  ──POST──►  /api/notifications/send
                                           /api/notifications/send-bulk
                                                    │
                                                    ▼
                                          Notification::send()
                                          Notification::sendBulk()
                                                    │
                         ┌──────────────────┬───────┼───────┬──────────────┐
                         ▼                  ▼       ▼       ▼              ▼
                    database           push(FCM)   email    sms     notification_
                    channel            channel     channel  channel  deliveries
                         │                  │       │       │         (tracking)
                         ▼                  ▼       ▼       ▼
                   notifications      FCM v1 API  SMTP   SMS API
                   table              (+ Legacy)
                         │
                         ▼
                  notification_counters
                  (unread_count)
```

---

## File Map

### Backend (PHP)

| File | Purpose |
|------|---------|
| `api/shared/helpers/notification.php` | **Main class** - `Notification` class with all send/read/FCM logic (~1100 lines) |
| `api/shared/helpers/mail.php` | Email sending helper (used by email channel) |
| `api/shared/helpers/sms.php` | SMS sending helper (used by SMS channel) |
| `api/v1/routes/notifications.php` | **API routes** - REST endpoints for notifications (~300 lines) |
| `api/v1/models/notification/` | MVC models, services, validators for notifications |
| `api/shared/config/.env` | Environment configuration (FCM keys, APP_URL, etc.) |
| `api/shared/config/constants.php` | PHP constants including Firebase config |
| `api/shared/config/firebase-service-account.json` | Firebase service account key (**NEVER commit!**) |

### Frontend (JS)

| File | Purpose |
|------|---------|
| `firebase-messaging-sw.js` | **Service Worker** at site root - handles background push notifications |
| `frontend/assets/js/firebase.js` | FCM registration, foreground notifications, token management |
| `admin/assets/js/fcm-init.js` | Admin panel FCM device registration |
| `admin/assets/js/pages/notification.js` | Admin notification management UI (~1600 lines) |

### Admin Panel (PHP)

| File | Purpose |
|------|---------|
| `admin/fragments/notification.php` | Notification management page with tabs (~800 lines) |

### Database

| File | Purpose |
|------|---------|
| `database/migrations/create_notification_tables.sql` | Creates 5 notification tables |
| `database/migrations/create_user_devices.sql` | Creates user_devices table |
| `database/migrations/alter_user_devices_fcm_token_nullable.sql` | Makes fcm_token nullable |

### Translations

| File | Purpose |
|------|---------|
| `languages/Notifications/en.json` | English translations (~350 lines) |
| `languages/Notifications/ar.json` | Arabic translations (~350 lines) |

### User Device Registration

| File | Purpose |
|------|---------|
| `api/v1/routes/user_devices.php` | Authenticated device registration (POST /api/user_devices) |
| `api/v1/routes/public/user_devices.php` | Public device registration (POST /api/public/user_devices) |
| `api/v1/routes/auth.php` | Auto-registers device on login via `_register_login_device()` |
| `api/shared/helpers/device_detector.php` | Detects device type/name from User-Agent |
| `api/v1/models/notification/services/UserDevicesService.php` | Device CRUD with dedup logic |

---

## Database Tables

### `notification_types`

| Column      | Type         | Notes                         |
|-------------|--------------|-------------------------------|
| id          | INT PK       | Auto increment                |
| code        | VARCHAR(50)  | UNIQUE - e.g. 'general', 'order_created' |
| name        | VARCHAR(100) | Display name                  |
| description | TEXT         | Optional                      |
| is_active   | TINYINT(1)   | 1=active, 0=disabled          |
| created_at  | DATETIME     |                               |
| updated_at  | DATETIME     |                               |

### `notification_channels`

| Column     | Type         | Notes                         |
|------------|--------------|-------------------------------|
| id         | INT PK       | Auto increment                |
| code       | VARCHAR(50)  | UNIQUE - 'database', 'push', 'email', 'sms' |
| name       | VARCHAR(100) | Display name                  |
| is_active  | TINYINT(1)   | 1=active, 0=disabled          |
| created_at | DATETIME     |                               |

### `notifications`

| Column               | Type            | Notes                        |
|----------------------|-----------------|------------------------------|
| id                   | BIGINT PK       | Auto increment               |
| tenant_id            | INT             | Multi-tenant support         |
| sender_entity_id     | INT NULL        | Who sent it                  |
| entity_id            | INT             | **Recipient ID**             |
| title                | VARCHAR(255)    |                              |
| message              | TEXT            |                              |
| data                 | JSON            | Extra data payload           |
| notification_type_id | INT FK          | References notification_types |
| priority             | ENUM            | low, normal, high, urgent    |
| is_read              | TINYINT(1)      | 0=unread, 1=read             |
| read_at              | DATETIME NULL   |                              |
| expires_at           | DATETIME NULL   |                              |
| sent_at              | DATETIME NULL   |                              |
| created_at           | DATETIME        |                              |
| updated_at           | DATETIME        |                              |

### `notification_counters`

| Column         | Type         | Notes                         |
|----------------|--------------|-------------------------------|
| id             | BIGINT PK    | Auto increment                |
| tenant_id      | INT          |                               |
| recipient_type | ENUM         | user, entity, tenant          |
| recipient_id   | INT          |                               |
| unread_count   | INT          | Cached count                  |
| updated_at     | DATETIME     |                               |

**Unique key:** `(tenant_id, recipient_type, recipient_id)`

### `notification_deliveries`

| Column          | Type         | Notes                          |
|-----------------|--------------|--------------------------------|
| id              | BIGINT PK    | Auto increment                 |
| notification_id | BIGINT FK    | References notifications       |
| channel_id      | INT FK       | References notification_channels |
| delivery_status | ENUM         | pending, sent, failed, delivered, read |
| attempts        | INT          | Default 0                      |
| sent_at         | DATETIME     |                                |
| error_message   | TEXT NULL    | Error details on failure       |
| created_at      | DATETIME     |                                |
| updated_at      | DATETIME     |                                |

### `user_devices`

| Column      | Type          | Notes                          |
|-------------|---------------|--------------------------------|
| id          | BIGINT PK     | Auto increment, UNSIGNED       |
| user_id     | INT           | NOT NULL                       |
| fcm_token   | TEXT NULL     | Firebase Cloud Messaging token |
| device_type | VARCHAR(20)   | web, android, ios, other       |
| device_name | VARCHAR(100)  | e.g. "Chrome on Windows"       |
| user_agent  | TEXT NULL     |                                |
| ip          | VARCHAR(45)   | IPv4 or IPv6                   |
| last_seen_at| DATETIME NULL |                                |
| is_active   | TINYINT(1)    | 1=active, 0=deregistered       |
| created_at  | DATETIME      |                                |
| updated_at  | DATETIME      |                                |

**Indexes:** user_id, is_active, (user_id + is_active), UNIQUE fcm_token(700)

---

## Configuration

### Environment Variables (`.env`)

```env
# Application
APP_NAME=QOOQZ
APP_URL=https://hcsfcs.top

# Notification Icon (relative path, appended to APP_URL for FCM)
APP_NOTIFICATION_ICON=/admin/assets/img/default-image.png

# Firebase Cloud Messaging
FCM_VAPID_KEY=BEzn1WFf...      # Web Push VAPID public key
FCM_PROJECT_ID=qooqz-2011       # Firebase project ID
FCM_SERVER_KEY=...               # Legacy API key (deprecated)
# FCM_SERVICE_ACCOUNT_PATH=...   # Path to service account JSON

# Channel toggles
# MAIL_ENABLED=false
# SMS_ENABLED=false
```

### Important: FCM Notification Icon

The `APP_LOGO_URL` constant is constructed as:
```
APP_URL + APP_NOTIFICATION_ICON = https://hcsfcs.top/admin/assets/img/default-image.png
```

**FCM v1 API requires absolute HTTPS URLs** for notification images, icons, and badges. A relative path will cause the icon to not display.

### Firebase Service Account

1. Go to Firebase Console > Project Settings > Service accounts
2. Click "Generate new private key"
3. Save as `api/shared/config/firebase-service-account.json`
4. **NEVER commit this file** (it's in `.gitignore`)

---

## Backend API

### Endpoints

#### GET /api/notifications
List notifications with pagination and filtering.

**Query params:**
- `id` - Get single notification by ID
- `tenant_id` - Filter by tenant
- `type_id` - Filter by notification type
- `recipient_id` / `entity_id` - Filter by recipient
- `is_read` - Filter by read status (0/1)
- `priority` - Filter by priority
- `order_by` - Sort field (default: created_at)
- `order_dir` - Sort direction (ASC/DESC)
- `limit` - Page size (default: 20)
- `offset` - Pagination offset

#### GET /api/notifications/unread-count
Get unread notification count.

**Query params:**
- `user_id` (required) - User ID

**Response:**
```json
{ "success": true, "unread_count": 5 }
```

#### POST /api/notifications/send
Send a notification to a single recipient via multiple channels.

**Body (JSON):**
```json
{
    "recipient_id": 123,
    "recipient_type": "user",
    "tenant_id": 1,
    "type_code": "general",
    "title": "Hello",
    "message": "Your order is ready",
    "data": "{\"order_id\": 456}",
    "channels": ["database", "push"],
    "priority": "normal",
    "expires_at": "2026-04-01 00:00:00",
    "sender_entity_id": 1,
    "device_ids": [10, 20]
}
```

**Response:**
```json
{
    "success": true,
    "notification_id": 789,
    "channels": {
        "database": { "success": true },
        "push": { "success": true, "sent": 2, "failed": 0 }
    }
}
```

#### POST /api/notifications/send-bulk
Send a notification to multiple users at once.

**Body (JSON):**
```json
{
    "user_ids": [1, 2, 3, 4, 5],
    "type_code": "general",
    "title": "Announcement",
    "message": "New features available!",
    "data": "{}",
    "channels": ["database", "push"],
    "tenant_id": 1
}
```

**Limits:** Maximum 5000 user IDs per request.

**Response:**
```json
{
    "success": true,
    "total": 5,
    "success_count": 4,
    "fail_count": 1,
    "results": [...]
}
```

#### POST /api/notifications/mark-read
Mark a notification as read.

**Body:** `{ "id": 789 }`

#### POST /api/notifications
Create a notification (via controller).

#### PUT /api/notifications
Update a notification.

#### DELETE /api/notifications
Delete a notification. **Query param:** `id`

---

## Notification Class Methods

### Public Methods

#### `Notification::setPDO(PDO $pdo): void`
Initialize the database connection. Must be called before any other method.

#### `Notification::send(...): array`
Send a notification to a single recipient.

**Parameters:**
| # | Name | Type | Default | Description |
|---|------|------|---------|-------------|
| 1 | recipientId | int | required | Recipient user/entity/tenant ID |
| 2 | recipientType | string | 'user' | 'user', 'entity', or 'tenant' |
| 3 | tenantId | int | 1 | Tenant ID for multi-tenancy |
| 4 | typeCode | string | 'general' | Notification type code |
| 5 | title | string | '' | Notification title |
| 6 | message | string | '' | Notification body |
| 7 | data | array | [] | Extra data payload (JSON) |
| 8 | channels | array | ['database'] | Channels: database, push, email, sms |
| 9 | priority | string | 'normal' | low, normal, high, urgent |
| 10 | expiresAt | ?string | null | Expiry datetime |
| 11 | senderEntityId | ?int | null | Sender entity ID |
| 12 | deviceIds | array | [] | Specific device IDs for push (max 100) |

#### `Notification::sendBulk(...): array`
Send to multiple users. Internally calls `send()` in a loop.

**Parameters:**
| # | Name | Type | Default | Description |
|---|------|------|---------|-------------|
| 1 | userIds | array | required | Array of user IDs (max 5000) |
| 2 | typeCode | string | required | Notification type code |
| 3 | title | string | required | Notification title |
| 4 | message | string | required | Notification body |
| 5 | data | array | [] | Extra data payload |
| 6 | channels | array | ['database'] | Delivery channels |
| 7 | tenantId | int | 1 | Tenant ID |

#### `Notification::getUserNotifications(...): array`
Get paginated notifications for a user.

#### `Notification::getUnreadCount(int $recipientId, string $recipientType, int $tenantId): int`
Get unread notification count from `notification_counters`.

#### `Notification::markAllRead(int $recipientId, string $recipientType, int $tenantId): bool`
Mark all notifications as read and reset counter.

### Device Token Management

#### `Notification::registerDeviceToken(int $userId, string $fcmToken, string $deviceType, string $deviceName, string $userAgent, string $ip): bool`
Register an FCM push token for a user's device.

#### `Notification::deregisterDeviceToken(string $fcmToken): bool`
Deactivate an FCM token (sets `is_active = 0`).

### Convenience Methods (Pre-built Templates)

| Method | Type Code | Description |
|--------|-----------|-------------|
| `orderCreated()` | order_created | New order notification |
| `orderStatusChanged()` | order_status | Order status update |
| `orderShipped()` | order_shipped | Shipping notification |
| `paymentSuccess()` | payment_success | Payment confirmed |
| `paymentFailed()` | payment_failed | Payment failed |
| `returnRequested()` | return_requested | Return request created |
| `newDeviceLogin()` | security | New device login alert |
| `passwordChanged()` | security | Password change confirmation |
| `supportTicketReply()` | support | Support ticket reply |

---

## FCM Push Notifications

### How It Works

1. **Token Registration:** When a user visits the site, `firebase.js` requests notification permission and obtains an FCM token. This token is saved via `POST /api/user_devices`.

2. **Sending:** When `Notification::send()` is called with `'push'` channel:
   - Fetches active FCM tokens for the recipient from `user_devices` table
   - Tries FCM v1 API first (OAuth2 + Service Account)
   - Falls back to Legacy API if v1 fails
   - Records delivery status in `notification_deliveries`

3. **Receiving:**
   - **Foreground:** `firebase.js` → `handleForegroundMessage()` → browser Notification API
   - **Background:** `firebase-messaging-sw.js` → `onBackgroundMessage()` → `showNotification()`

### FCM v1 API Payload Structure

```json
{
    "message": {
        "token": "device_fcm_token",
        "notification": {
            "title": "Notification Title",
            "body": "Notification message body",
            "image": "https://hcsfcs.top/admin/assets/img/default-image.png"
        },
        "data": {
            "notification_id": "123",
            "click_action": "FLUTTER_NOTIFICATION_CLICK"
        },
        "android": {
            "priority": "high",
            "notification": {
                "click_action": "FLUTTER_NOTIFICATION_CLICK",
                "icon": "ic_notification"
            }
        },
        "webpush": {
            "notification": {
                "icon": "https://hcsfcs.top/admin/assets/img/default-image.png",
                "badge": "https://hcsfcs.top/admin/assets/img/default-image.png"
            },
            "fcm_options": {
                "link": "/"
            }
        }
    }
}
```

### Device Registration Flow

Devices are registered in 3 ways:

1. **On Login** (`auth.php`): Automatically via `_register_login_device()` — no FCM token yet
2. **Public API** (`POST /api/public/user_devices`): Browser registration with optional FCM token
3. **Firebase JS** (`POST /api/user_devices`): After FCM permission granted, sends FCM token

**Deduplication logic** (in `UserDevicesService::create()`):
- First tries to match by `fcm_token` (if provided)
- Then falls through to match by `user_id + user_agent`
- Updates existing record rather than creating duplicates

### Security Rules

- Device write operations must use `$_SESSION['user_id']` (not `$_GET['user_id']`)
- FCM token queries must include `AND user_id = ?` to prevent cross-user hijacking
- `user_agent` must be truncated to 512 chars for consistent dedup matching

---

## Frontend Integration

### Loading FCM (frontend/partials/footer.php)

FCM is loaded conditionally:
```php
if (FCM_ENABLED && isset($_SESSION['user_id'])) {
    // Load firebase.js and pass config via APP_CONFIG
}
```

### firebase.js Flow

1. Initialize Firebase with project config
2. Request notification permission
3. Get FCM messaging token using VAPID key
4. POST token to `/api/user_devices`
5. Listen for foreground messages via `onMessage()`
6. On token refresh, re-register
7. On page unload (if needed), deregister via `/api/user_devices/deregister`

### Service Worker (firebase-messaging-sw.js)

- Must be at site root (`/firebase-messaging-sw.js`)
- Handles background messages via `onBackgroundMessage()`
- Shows system notification with icon and badge
- Handles notification click → navigates to URL from `data.click_url`

---

## Admin Panel

### Notification Management (admin/fragments/notification.php)

The admin panel has **7 tabs**:

| Tab | Description |
|-----|-------------|
| Types | Manage notification types (CRUD) |
| List | View all notifications with filters |
| Channels | Manage notification channels (CRUD) |
| Counters | View notification counters per user |
| Deliveries | View delivery status per notification per channel |
| Devices | View registered user devices and FCM tokens |
| Bulk Send | Send notifications to multiple selected users |

### Bulk Send Features

- Search/filter users by name, email, tenant, entity
- Multi-select checkboxes for user selection
- Select all on current page
- Send to selected users via chosen channels
- Maximum 5000 users per bulk send
- Progress tracking with success/fail counts

---

## Translations

Translation files are at `languages/Notifications/{en,ar}.json`.

### Key Sections

| Key Prefix | Description |
|------------|-------------|
| `tab.*` | Tab labels (types, list, channels, counters, deliveries, devices) |
| `type.*` | Notification type form fields |
| `channel.*` | Channel form fields |
| `notification.*` | Notification list/form fields |
| `counter.*` | Counter display labels |
| `delivery.*` | Delivery tracking labels |
| `device.*` | Device information labels |
| `send.*` | Send notification form labels |
| `bulk_send.*` | Bulk send UI labels |
| `status.*` | Status labels (pending, sent, failed, etc.) |
| `priority.*` | Priority labels (low, normal, high, urgent) |
| `action.*` | Action button labels |
| `confirm.*` | Confirmation dialog messages |
| `error.*` | Error messages |

---

## Troubleshooting

### Notification icons not showing

**Cause:** `APP_LOGO_URL` must be an **absolute HTTPS URL** (e.g. `https://example.com/icon.png`). Relative paths like `/images/logo.png` won't work with FCM.

**Fix:** Set `APP_URL` in `.env` and optionally `APP_NOTIFICATION_ICON`:
```env
APP_URL=https://your-domain.com
APP_NOTIFICATION_ICON=/admin/assets/img/default-image.png
```

### FCM tokens not being obtained

**Cause:** VAPID key not loaded. The frontend doesn't load `.env` — only `api/bootstrap.php` does.

**Fix:** Ensure `footer.php` loads `.env` via `putenv()` before including `constants.php`.

### Duplicate device records

**Cause:** `user_agent` length mismatch between login registration (512 chars) and FCM registration.

**Fix:** Always truncate `user_agent` to 512 characters before saving.

### "Invalid JWT Signature" error

**Cause:** Firebase service account key was exposed on public GitHub. Google auto-revokes exposed keys.

**Fix:** Generate a new key from Firebase Console and save to `api/shared/config/firebase-service-account.json`.

### sendBulk() is slow for large batches

**Current behavior:** `sendBulk()` calls `send()` in a loop (one by one). For 1000+ users, this can be slow.

**Workaround:** FCM v1 API sends one HTTP request per device token. For large batches, consider:
- Sending in background (async job/queue)
- Using `database` channel only for large batches
- Batching FCM calls (the current implementation already handles multiple tokens per user)

### FCM v1 API not working

1. Check `firebase-service-account.json` exists and is valid
2. Check `FCM_PROJECT_ID` is set in `.env`
3. Check service account has "Firebase Cloud Messaging API" enabled
4. Check the access token generation in `getFcmAccessToken()` logs

### Legacy FCM API deprecated

Google deprecated the Legacy FCM API. The system falls back to it only if v1 API is not configured. To use v1 API:
1. Set up a service account JSON file
2. Set `FCM_PROJECT_ID` in `.env`
3. Enable "Firebase Cloud Messaging API (V1)" in Google Cloud Console

### Custom Logo / Image in Notifications

The FCM payload supports custom icon and image URLs via the `$data` array:

```php
Notification::send(
    recipientId: $userId,
    data: [
        'icon_url'  => 'https://example.com/logos/store.png',   // Custom notification icon
        'image_url' => 'https://example.com/covers/banner.jpg', // Large image in notification
        'click_url' => '/orders/123',                           // Click destination
    ],
    channels: ['database', 'push']
);
```

**FCM Payload includes:**
- `notification.image` → From `$data['image_url']` or `APP_LOGO_URL`
- `webpush.notification.icon` → From `$data['icon_url']` or `APP_LOGO_URL`
- `webpush.notification.image` → Large banner image
- `data.site_name` → App name (from `APP_NAME` constant)
- `data.site_url` → App URL (from `APP_URL` constant)

The service worker (`firebase-messaging-sw.js`) also checks `data.icon_url` and `data.image_url` as fallbacks.

---

## Related Documentation

- [Store Builder Documentation](STORE_BUILDER.md) — Dynamic store page builder system
