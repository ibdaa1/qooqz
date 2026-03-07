-- Migration: Add payment_method column to orders table if not exists
-- Required by POS cashier system to store and filter by payment method per order.

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cash' AFTER sales_channel;
