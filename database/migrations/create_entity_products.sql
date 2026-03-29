-- =====================================================
-- Entity Products Table
-- Allows per-entity product overrides (price, stock, etc.)
-- =====================================================

CREATE TABLE IF NOT EXISTS entity_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tenant_id            INT(10) UNSIGNED NOT NULL,
    entity_id            BIGINT UNSIGNED NOT NULL,
    product_id           BIGINT UNSIGNED NOT NULL,

    price                DECIMAL(15,2) NULL,
    compare_at_price     DECIMAL(15,2) NULL,
    stock_quantity       INT NOT NULL DEFAULT 0,
    low_stock_threshold  INT NOT NULL DEFAULT 5,

    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    is_featured          TINYINT(1) NOT NULL DEFAULT 0,

    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_entity_product (entity_id, product_id),
    KEY idx_tenant_entity (tenant_id, entity_id),
    KEY idx_product (product_id),

    FOREIGN KEY (tenant_id)  REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (entity_id)  REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
