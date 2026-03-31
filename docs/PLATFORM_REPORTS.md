# Platform Reports & Analytics

## Overview

The Platform Report module provides comprehensive analytics and reporting for the entire platform. It supports 10 report types, live data aggregation from multiple tables, time-series charts, data export, and scheduled reports.

## Architecture

### Backend Files

| File | Purpose |
|------|---------|
| `api/v1/routes/platform_report.php` | API route handler (GET/POST) |
| `api/v1/models/platform_report/controllers/PlatformReportController.php` | Controller (delegates to service) |
| `api/v1/models/platform_report/services/PlatformReportService.php` | Service layer (orchestrates reports) |
| `api/v1/models/platform_report/repositories/PdoPlatformReportRepository.php` | Repository (all SQL queries) |
| `api/v1/models/platform_report/validators/PlatformReportValidator.php` | Input validation |

### Frontend Files

| File | Purpose |
|------|---------|
| `admin/fragments/platform_report.php` | HTML template (filters, cards, chart, table) |
| `admin/assets/js/pages/platform_report.js` | JavaScript (API calls, rendering, Chart.js) |
| `admin/assets/css/pages/platform_report.css` | Styles |
| `languages/PlatformReport/{lang}.json` | Translation strings |

### Database Tables

| Table | Purpose |
|-------|---------|
| `report_types` | Lookup table for available report types |
| `platform_report_stats` | Cached aggregated metrics (JSON) |
| `report_schedules` | Automated report scheduling |
| `report_exports` | Export request tracking |

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
- **Date Range:** Start date and end date pickers
- **Group By:** Day, week, or month aggregation
- **Tenant:** Searchable text input with autocomplete (super admin only, supports millions of tenants)
- **Entity/Store:** Dropdown that auto-reloads when tenant changes

### Report Results
- **Metric Cards:** Color-coded cards with icons showing key metrics
- **Chart:** Chart.js bar/line chart with time series data (dual Y-axis)
- **Data Table:** Detailed table for top products, top entities, top ads, or metric key-value pairs

### Export
- Excel, PDF, CSV export buttons (creates export request for background processing)

## Tenant Search

The tenant filter uses a debounced searchable text input instead of a dropdown, to support platforms with large numbers of tenants. When a tenant is selected:
1. The hidden `prTenantId` input is set with the tenant ID
2. The entity dropdown automatically reloads, filtered by the selected tenant
3. Clearing the tenant search resets to "All Tenants" and reloads all entities

## Technical Notes

### PDO Parameter Binding
All SQL queries use unique named parameters to avoid PDO's restriction on reusing named parameters in native prepared statements. Complex queries with multiple subqueries are split into separate executions.

### Chart.js Loading
Chart.js is loaded asynchronously from CDN with a fallback retry mechanism for fragment/SPA mode where the script might not be loaded yet when rendering occurs.

### Error Handling
- SQL errors are caught and returned as JSON error responses
- `delivery_orders` queries are wrapped in try/catch (table may not exist in all deployments)
- Frontend shows "No data" message on API errors
