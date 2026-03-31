-- =====================================================
-- Entity Product Variants Table
-- Per-entity variant stock overrides (stock, active, featured)
-- Pricing is managed via the unified product_pricing table
-- =====================================================

CREATE TABLE IF NOT EXISTS entity_product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    tenant_id           INT(10) UNSIGNED NOT NULL,
    entity_id           BIGINT UNSIGNED NOT NULL,
    product_id          BIGINT UNSIGNED NOT NULL,
    variant_id          BIGINT UNSIGNED NOT NULL,

    stock_quantity      INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    manage_stock        TINYINT(1) NOT NULL DEFAULT 1,
    stock_status        ENUM('in_stock', 'out_of_stock', 'unlimited') NOT NULL DEFAULT 'in_stock',

    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    is_featured         TINYINT(1) NOT NULL DEFAULT 0,

    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_entity_variant (entity_id, variant_id),
    KEY idx_tenant_entity (tenant_id, entity_id),
    KEY idx_variant (variant_id),
    KEY idx_product (product_id),

    FOREIGN KEY (tenant_id)  REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (entity_id)  REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
