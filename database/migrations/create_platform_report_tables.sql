-- ============================================================
-- Platform Report & Analytics System
-- Multi-tenant reporting with aggregated statistics
-- ============================================================

-- 1) Report Types (lookup table)
CREATE TABLE IF NOT EXISTS `report_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_key` varchar(100) NOT NULL COMMENT 'e.g. sales_overview, revenue_profit',
  `title_en` varchar(255) NOT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `category` enum('sales','finance','products','ads','logistics','customers','platform') NOT NULL DEFAULT 'sales',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_key` (`type_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default report types
INSERT INTO `report_types` (`type_key`, `title_en`, `title_ar`, `category`, `sort_order`) VALUES
('sales_overview',        'Sales Overview',             'نظرة عامة على المبيعات',     'sales',     1),
('revenue_profit',        'Revenue & Profit',           'الإيرادات والأرباح',          'finance',   2),
('orders_performance',    'Orders Performance',         'أداء الطلبات',                'sales',     3),
('products_performance',  'Products Performance',       'أداء المنتجات',               'products',  4),
('ads_performance',       'Ads Performance',            'أداء الإعلانات',              'ads',       5),
('returns_complaints',    'Returns & Complaints',       'المرتجعات والشكاوى',          'logistics', 6),
('entities_performance',  'Entities Performance',       'أداء المتاجر',                'sales',     7),
('customer_behavior',     'Customer Behavior',          'سلوك العملاء',                'customers', 8),
('platform_health',       'Platform Health',            'صحة المنصة',                  'platform',  9);

-- 2) Platform Report Stats (main aggregation table)
CREATE TABLE IF NOT EXISTS `platform_report_stats` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = platform-wide stat',
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = all entities; set for entity-level stats',
  `report_type` varchar(100) NOT NULL,
  `period_type` enum('daily','weekly','monthly','yearly','custom') NOT NULL DEFAULT 'daily',
  `period_date` date NOT NULL COMMENT 'The date this stat covers',
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `metrics` JSON NOT NULL COMMENT 'Aggregated metrics as JSON',
  `generated_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type_period` (`tenant_id`, `report_type`, `period_type`, `period_date`),
  KEY `idx_entity_type_period` (`entity_id`, `report_type`, `period_type`, `period_date`),
  KEY `idx_report_type` (`report_type`),
  KEY `idx_period_date` (`period_date`),
  KEY `idx_period_type_date` (`period_type`, `period_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Report Schedules (for automated generation)
CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `recipients_email` text DEFAULT NULL COMMENT 'Comma-separated email addresses',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`, `is_active`),
  KEY `idx_next_run` (`next_run_at`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Report Exports (tracking export requests)
CREATE TABLE IF NOT EXISTS `report_exports` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `export_format` enum('excel','pdf','csv') NOT NULL DEFAULT 'excel',
  `filters` JSON DEFAULT NULL COMMENT 'Filters used when generating',
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `requested_by` int(11) UNSIGNED DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  KEY `idx_requested_by` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
