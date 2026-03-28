-- Create notification system tables if they don't exist
-- Run this migration to ensure the notification system works properly

-- 1. Notification Types (أنواع الإشعارات)
CREATE TABLE IF NOT EXISTS `notification_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default notification types
INSERT IGNORE INTO `notification_types` (`code`, `name`, `is_active`) VALUES
    ('general',       'عام - General',              1),
    ('order_created', 'طلب جديد - Order Created',   1),
    ('order_status',  'حالة الطلب - Order Status',   1),
    ('order_shipped', 'شحن الطلب - Order Shipped',   1),
    ('payment',       'دفع - Payment',              1),
    ('promotion',     'عرض - Promotion',             1),
    ('system',        'نظام - System',               1),
    ('chat',          'محادثة - Chat',               1),
    ('auction',       'مزاد - Auction',              1);

-- 2. Notification Channels (قنوات الإشعارات)
CREATE TABLE IF NOT EXISTS `notification_channels` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default channels
INSERT IGNORE INTO `notification_channels` (`code`, `name`, `is_active`) VALUES
    ('database', 'Database',     1),
    ('push',     'Push (FCM)',   1),
    ('email',    'Email',        1),
    ('sms',      'SMS',          1);

-- 3. Notifications (الإشعارات الرئيسية)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `sender_entity_id` INT UNSIGNED NULL,
    `entity_id` INT UNSIGNED NOT NULL COMMENT 'recipient user/entity id',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `data` JSON NULL,
    `notification_type_id` INT UNSIGNED NULL,
    `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_notifications_entity` (`entity_id`, `tenant_id`),
    INDEX `idx_notifications_type` (`notification_type_id`),
    INDEX `idx_notifications_sent` (`sent_at`),
    INDEX `idx_notifications_priority` (`priority`),
    FOREIGN KEY (`notification_type_id`) REFERENCES `notification_types`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notification Counters (عدادات الإشعارات غير المقروءة)
CREATE TABLE IF NOT EXISTS `notification_counters` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `recipient_type` ENUM('user','entity','tenant') NOT NULL DEFAULT 'user',
    `recipient_id` INT UNSIGNED NOT NULL,
    `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_counter` (`tenant_id`, `recipient_type`, `recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Notification Deliveries (سجل التسليم)
CREATE TABLE IF NOT EXISTS `notification_deliveries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `notification_id` BIGINT UNSIGNED NOT NULL,
    `channel_id` INT UNSIGNED NOT NULL,
    `delivery_status` ENUM('pending','sent','failed','delivered','read') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_at` TIMESTAMP NULL,
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_deliveries_notification` (`notification_id`),
    INDEX `idx_deliveries_status` (`delivery_status`),
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`channel_id`) REFERENCES `notification_channels`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
