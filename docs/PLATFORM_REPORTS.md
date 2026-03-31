# Platform Reports & Analytics

## Overview

The Platform Report module provides comprehensive analytics and reporting for the entire platform. It supports 10 report types, live data aggregation from multiple tables, time-series charts, data export, and scheduled reports.

## Architecture

### Backend Files

| File | Purpose |
|------|---------|
| `api/v1/routes/platform_report.php` | API route handler (GET/POST) |
| `api/v1/models/platform_report/controllers/PlatformReportController.php` | Controller (delegates to service) |
| `api/v1/models/platform_report/controllers/ExportController.php` | Export controller (audit logging) |
| `api/v1/models/platform_report/controllers/ScheduleController.php` | Schedule controller |
| `api/v1/models/platform_report/services/PlatformReportService.php` | Service layer (orchestrates reports) |
| `api/v1/models/platform_report/services/Report/GenerateReportService.php` | Report generation service |
| `api/v1/models/platform_report/services/Report/AggregationService.php` | Aggregation service |
| `api/v1/models/platform_report/services/Export/ExportReportService.php` | Export service (server audit) |
| `api/v1/models/platform_report/services/Schedule/ScheduleService.php` | Schedule service |
| `api/v1/models/platform_report/repositories/PdoPlatformReportRepository.php` | Repository (all SQL queries) |
| `api/v1/models/platform_report/validators/PlatformReportValidator.php` | Input validation |

### Frontend Files

| File | Purpose |
|------|---------|
| `admin/fragments/platform_report.php` | HTML template (filters, cards, chart, table) |
| `admin/assets/js/pages/platform_report.js` | JavaScript (API calls, rendering, Chart.js, exports) |
| `admin/assets/css/pages/platform_report.css` | Styles (RTL, responsive, theming) |
| `languages/PlatformReport/en.json` | English translation strings |
| `languages/PlatformReport/ar.json` | Arabic translation strings |

### Database Tables

| Table | Purpose |
|-------|---------|
| `report_types` | Lookup table for available report types |
| `platform_report_stats` | Cached aggregated metrics (JSON) |
| `report_schedules` | Automated report scheduling |
| `report_exports` | Export request tracking / audit log |

Schema: `database/migrations/create_platform_report_tables.sql`

## API Endpoints

Base URL: `/api/platform_report`

### GET Endpoints

#### List Report Types
```
GET /api/platform_report?action=types
```
Returns all active report types.

#### Dashboard Summary
```
GET /api/platform_report?action=dashboard&tenant_id=X
```
Returns today's and this month's summary (orders, revenue, customers).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `tenant_id` | int | No | Filter by tenant |

#### Generate Report
```
GET /api/platform_report?action=report&report_type=sales_overview&start_date=2025-01-01&end_date=2025-12-31&group_by=day
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `report_type` | string | Yes | One of the 10 report types (see below) |
| `start_date` | date | Yes | Start date (YYYY-MM-DD) |
| `end_date` | date | Yes | End date (YYYY-MM-DD) |
| `group_by` | string | No | `day` (default), `week`, `month` |
| `tenant_id` | int | No | Filter by tenant |
| `entity_id` | int | No | Filter by entity/store |

**Response:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "success": true,
    "report_type": "sales_overview",
    "period": { "start": "2025-01-01", "end": "2025-12-31" },
    "tenant_id": null,
    "entity_id": null,
    "metrics": { ... },
    "time_series": [
      { "period": "2025-01-01", "order_count": 5, "revenue": "1234.56" }
    ]
  }
}
```

#### List Exports
```
GET /api/platform_report?action=exports&tenant_id=X
```

#### List Schedules
```
GET /api/platform_report?action=schedules&tenant_id=X
```

### POST Endpoints

#### Request Export
```
POST /api/platform_report?action=export
Content-Type: application/json

{
  "report_type": "sales_overview",
  "start_date": "2025-01-01",
  "end_date": "2025-12-31",
  "export_format": "excel"
}
```
Supported formats: `excel`, `pdf`, `csv`

#### Create Schedule
```
POST /api/platform_report?action=schedule
Content-Type: application/json

{
  "report_type": "sales_overview",
  "frequency": "daily",
  "recipients_email": "admin@example.com"
}
```

## Report Types

### 1. `sales_overview` – Sales Overview
**Metrics:** total_orders, total_revenue, total_tax, total_shipping, total_discounts, avg_order_value, unique_customers, completed_orders, cancelled_orders, refunded_orders, paid_orders  
**Time Series:** order_count, revenue (grouped by day/week/month)  
**Data Source:** `orders` table  
**Filters:** tenant_id, entity_id

### 2. `revenue_profit` – Revenue & Profit
**Metrics:** gross_revenue, net_revenue, total_discounts, total_tax, total_shipping, total_commissions  
**Time Series:** order_count, revenue  
**Data Source:** `orders` + `commission_invoices` tables  
**Filters:** tenant_id, entity_id

### 3. `orders_performance` – Orders Performance
**Metrics:** total_orders, pending_orders, confirmed_orders, processing_orders, shipped_orders, delivered_orders, cancelled_orders, refunded_orders, online_orders, pos_orders, paid_count, payment_pending_count, avg_delivery_hours + delivery stats (total_deliveries, pending_deliveries, in_transit_deliveries, completed_deliveries, cancelled_deliveries, failed_deliveries, total_delivery_fees, total_provider_payouts, avg_delivery_minutes)  
**Time Series:** order_count, revenue  
**Data Source:** `orders` + `delivery_orders` tables  
**Filters:** tenant_id, entity_id

### 4. `products_performance` – Products Performance
**Metrics:** total_products, active_products, out_of_stock, low_stock, products_sold_count, total_units_sold, product_views, product_clicks, add_to_cart_events, product_favorites, product_purchases, top_products[]  
**Time Series:** units_sold, revenue  
**Data Source:** `products` + `order_items` + `core_events` tables  
**Filters:** tenant_id, entity_id (for top_products)

### 5. `ads_performance` – Ads Performance
**Metrics:** active_campaigns, total_impressions, total_clicks, ctr, total_interactions, top_ads[]  
**Time Series:** views, clicks  
**Data Source:** `ad_stats` + `ads` + `ad_campaigns` tables  
**Filters:** tenant_id

### 6. `returns_complaints` – Returns & Complaints
**Metrics:** total_returns, pending_returns, approved_returns, rejected_returns, completed_returns, total_tickets, open_tickets, resolved_tickets  
**Time Series:** return_count  
**Data Source:** `returns` + `support_tickets` tables  
**Filters:** tenant_id

### 7. `entities_performance` – Entities Performance
**Metrics:** total_entities, active_entities, pending_entities, suspended_entities, top_entities[]  
**Time Series:** order_count, revenue  
**Data Source:** `entities` + `orders` tables  
**Filters:** tenant_id

### 8. `customer_behavior` – Customer Behavior
**Metrics:** new_users, total_carts, abandoned_carts, converted_carts, cart_conversion_rate, repeat_customers, wishlist_items  
**Time Series:** new_users  
**Data Source:** `users` + `carts` + `orders` + `wishlist_items` tables  
**Filters:** tenant_id (for repeat_customers)

### 9. `delivery_performance` – Delivery Performance
**Metrics:** total_deliveries, pending_deliveries, assigned_deliveries, in_transit_deliveries, completed_deliveries, cancelled_deliveries, failed_deliveries, total_delivery_fees, total_provider_payouts, avg_delivery_minutes  
**Time Series:** delivery_count, delivery_fees  
**Data Source:** `delivery_orders` + `orders` tables  
**Filters:** tenant_id, entity_id

### 10. `platform_health` – Platform Health (Super Admin only)
**Metrics:** total_users, active_users, total_tenants, active_tenants, total_entities, total_products, period_orders, period_revenue, active_subscriptions  
**Time Series:** order_count, revenue  
**Data Source:** `users`, `tenants`, `entities`, `products`, `orders`, `subscriptions` tables  
**Filters:** None (global view)

## Admin UI Features

### Dashboard Summary
- Today's orders, revenue, customers
- This month's orders, revenue, customers, average order value

### Filters
- **Report Type:** Dropdown with 10 report types (platform_health is super admin only)
- **Date Range:** Start date and end date pickers (HTML5 `type="date"` inputs)
- **Group By:** Day, week, or month aggregation
- **Tenant:** Searchable text input with autocomplete (super admin only, supports millions of tenants)
- **Entity/Store:** Dropdown that auto-reloads when tenant changes

### Report Results
- **Metric Cards:** Color-coded cards with icons showing key metrics
- **Chart:** Chart.js bar/line chart with time series data (dual Y-axis)
- **Data Table:** Detailed table for top products, top entities, top ads, or metric key-value pairs

### Export

Reports are generated and downloaded **client-side** (no server-side file creation):

- **CSV:** UTF-8 file with BOM prefix (`\uFEFF`) for Arabic/Excel compatibility. Includes report metadata header (type, period) followed by data rows. Filename: `report_[type].csv`
- **Excel:** HTML table format that Excel can open (`.xls`). Includes styled headers (blue with white text), border formatting, and RTL direction for Arabic. Includes UTF-8 BOM. Filename: `report_[type].xls`
- **PDF:** Opens a browser print dialog in a new window with formatted report. Includes print-ready styles with striped rows. 500ms delay before `window.print()` to allow rendering. If popups are blocked, shows an alert message.

All exports include:
- Report type and period metadata
- RTL text direction for Arabic content
- Proper text alignment (`text-align: right` for RTL)
- Server-side audit logging via `POST /api/platform_report?action=export` to `report_exports` table

## RTL (Right-to-Left) Support

### Detection
The PHP fragment detects RTL languages and sets `dir="rtl"` on the container:
```php
$dir = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
```

### CSS RTL Rules
- Container: `direction: rtl; text-align: right`
- Table cells: `text-align: right`
- Metric cards: `flex-direction: row-reverse` (icon moves to right side)
- Filter labels: `text-align: right`
- Buttons: `flex-direction: row-reverse` (emoji moves to right)
- Export section: `direction: rtl`
- Section titles: `text-align: right`
- **Date inputs:** `direction: ltr; text-align: right` (dates must read left-to-right even in RTL context)
- **Select dropdowns:** `text-align: right` with appropriate padding
- **Text transforms:** Disabled (`text-transform: none`) for Arabic labels and table headers

### JavaScript RTL
- Chart.js Y-axis positions are swapped: primary axis on right, secondary on left
- Number formatting uses `ar-SA` locale for Arabic (`toLocaleString`)
- Export files include `dir="rtl"` attribute on body/html elements
- Export tables include `direction: rtl` and `text-align: right` CSS

### Translation
- All UI strings are loaded from `languages/PlatformReport/{lang}.json`
- Arabic translations cover 139+ keys
- Missing translations fall back to English key name

## Responsive Design

| Breakpoint | Layout Changes |
|------------|---------------|
| **1024px+** | Full desktop layout with auto-fit grids, 350px chart height |
| **768px** | Filters stack vertically, summary cards single-column, chart 250px, export buttons stack |
| **480px** | Compact metrics grid (2 columns), smaller fonts, chart 200px, reduced padding |

- Tables use `overflow-x: auto` for horizontal scrolling on small screens
- Chart uses `responsive: true` and `maintainAspectRatio: false`
- Canvas width forced to `100%` for proper desktop rendering

## Tenant Search

The tenant filter uses a debounced searchable text input instead of a dropdown, to support platforms with large numbers of tenants. When a tenant is selected:
1. The hidden `prTenantId` input is set with the tenant ID
2. The entity dropdown automatically reloads, filtered by the selected tenant
3. Clearing the tenant search resets to "All Tenants" and reloads all entities

## Technical Notes

### PDO Parameter Binding
All SQL queries use unique named parameters to avoid PDO's restriction on reusing named parameters in native prepared statements. Complex queries with multiple subqueries are split into separate executions.

### Chart.js Loading
Chart.js is loaded asynchronously from CDN via `ensureChartJs()` promise. The function checks if `Chart` is already defined, and if not, dynamically injects a script tag. This avoids the "Chart is not defined" error in fragment/SPA mode. After chart creation, a 200ms delayed `resize()` call ensures proper dimensions on desktop.

### Fragment Mode (SPA)
The report page works in both standalone and fragment (AJAX-loaded) modes:
- CSS is loaded via `<link>` tags that `admin_core.js` processes explicitly
- The inline `<script>` sets `window.__PR_CONFIG` with PHP variables
- The external `platform_report.js` uses a polling mechanism (`setInterval`) to detect when `#platformReportApp` is in the DOM
- `window.page = { run: bootstrap }` is exposed for SPA navigation

### Error Handling
- SQL errors are caught and returned as JSON error responses
- `delivery_orders` queries are wrapped in try/catch (table may not exist in all deployments)
- Frontend shows "No data" message on API errors
- Export buttons show an alert if clicked before generating a report
- PDF export shows a "popup blocked" alert if `window.open` returns null
