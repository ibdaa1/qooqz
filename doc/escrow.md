CREATE TABLE escrow_transactions (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NOT NULL,

escrow_number VARCHAR(100) NOT NULL UNIQUE,

order_id BIGINT UNSIGNED NULL,

buyer_entity_id BIGINT UNSIGNED NOT NULL,
buyer_entity_type VARCHAR(50) NOT NULL,

seller_entity_id BIGINT UNSIGNED NOT NULL,
seller_entity_type VARCHAR(50) NOT NULL,

amount DECIMAL(15,2) NOT NULL,
currency_code VARCHAR(8) DEFAULT 'USD',

escrow_fee DECIMAL(15,2) DEFAULT 0,

status ENUM(
'pending',
'funded',
'in_transit',
'delivered',
'released',
'disputed',
'refunded',
'cancelled'
) DEFAULT 'pending',

auto_release_days INT DEFAULT 7,

funded_at DATETIME NULL,
shipped_at DATETIME NULL,
delivered_at DATETIME NULL,
released_at DATETIME NULL,
disputed_at DATETIME NULL,
resolved_at DATETIME NULL,

notes TEXT NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_tenant (tenant_id),
INDEX idx_status (status),
INDEX idx_buyer (buyer_entity_id),
INDEX idx_seller (seller_entity_id)

) ENGINE=InnoDB;
//////////////////
CREATE TABLE escrow_status_history (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NOT NULL,

escrow_id BIGINT UNSIGNED NOT NULL,

status ENUM(
'pending',
'funded',
'in_transit',
'delivered',
'released',
'disputed',
'refunded',
'cancelled'
) NOT NULL,

notes TEXT NULL,

changed_by_entity_id BIGINT UNSIGNED,
changed_by_entity_type VARCHAR(50),

ip_address VARCHAR(45),

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

INDEX idx_escrow (escrow_id),
INDEX idx_tenant (tenant_id)

) ENGINE=InnoDB;
///////////////////
CREATE TABLE escrow_disputes (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NOT NULL,

dispute_number VARCHAR(100) UNIQUE,

escrow_id BIGINT UNSIGNED NOT NULL,
order_id BIGINT UNSIGNED NULL,

raised_by_entity_id BIGINT UNSIGNED NOT NULL,
raised_by_entity_type VARCHAR(50) NOT NULL,

dispute_type ENUM(
'not_received',
'not_as_described',
'damaged',
'wrong_item',
'other'
) NOT NULL,

description TEXT NOT NULL,

status ENUM(
'open',
'under_review',
'resolved_buyer',
'resolved_seller',
'resolved_partial',
'closed'
) DEFAULT 'open',

resolution_type ENUM(
'refund_full',
'refund_partial',
'release_payment',
'cancelled'
),

refund_amount DECIMAL(15,2),

assigned_to INT UNSIGNED NULL,

resolved_at DATETIME NULL,

resolution_notes TEXT,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_tenant (tenant_id),
INDEX idx_escrow (escrow_id),
INDEX idx_status (status)

) ENGINE=InnoDB;
//////////////
CREATE TABLE escrow_dispute_evidence (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NOT NULL,

dispute_id BIGINT UNSIGNED NOT NULL,

file_url VARCHAR(500) NOT NULL,

uploaded_by_entity_id BIGINT UNSIGNED,
uploaded_by_entity_type VARCHAR(50),

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

INDEX idx_dispute (dispute_id)

) ENGINE=InnoDB;
////////////
CREATE TABLE escrow_ledger (

id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NOT NULL,

escrow_id BIGINT UNSIGNED NOT NULL,

entity_id BIGINT UNSIGNED NOT NULL,
entity_type VARCHAR(50) NOT NULL,

transaction_type ENUM(
'fund',
'fee',
'release',
'refund',
'partial_refund'
) NOT NULL,

amount DECIMAL(15,2) NOT NULL,

currency_code VARCHAR(8) DEFAULT 'USD',

notes TEXT,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

INDEX idx_escrow (escrow_id),
INDEX idx_entity (entity_id)

) ENGINE=InnoDB;



