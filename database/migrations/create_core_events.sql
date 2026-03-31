-- Migration: create_core_events.sql
-- Creates the core_events table for analytics tracking.
-- Run this once on your MySQL/MariaDB database.

CREATE TABLE IF NOT EXISTS core_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    entity_type ENUM('product','entity','brand','category','job','auction') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,

    user_id INT NULL,
    session_id VARCHAR(64) NULL,

    event_type ENUM(
        'view',
        'click',
        'favorite',
        'contact',
        'add_to_cart',
        'purchase'
    ) NOT NULL,

    value DECIMAL(10,2) NULL,

    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_event (event_type),
    INDEX idx_session (session_id)
);
