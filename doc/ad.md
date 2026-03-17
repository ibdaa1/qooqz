الاعلانات

CREATE TABLE ad_campaigns (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NULL,
entity_id BIGINT UNSIGNED NULL,

name VARCHAR(255) NOT NULL,

budget DECIMAL(12,2) DEFAULT 0.00,
currency_id SMALLINT UNSIGNED NOT NULL,

pricing_model ENUM('fixed','cpm','cpc') DEFAULT 'fixed',

start_date DATETIME,
end_date DATETIME,

status ENUM('draft','active','paused','completed') DEFAULT 'draft',

created_by INT UNSIGNED NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

-- العلاقات
FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
FOREIGN KEY (currency_id) REFERENCES currencies(id),
FOREIGN KEY (created_by) REFERENCES users(id)
);
////////////////
CREATE TABLE ad_campaigns (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

tenant_id INT UNSIGNED NULL,
entity_id BIGINT UNSIGNED NULL,

name VARCHAR(255) NOT NULL,

budget DECIMAL(12,2) DEFAULT 0.00,
currency_id SMALLINT UNSIGNED NOT NULL,

pricing_model ENUM('fixed','cpm','cpc') DEFAULT 'fixed',

start_date DATETIME,
end_date DATETIME,

status ENUM('draft','active','paused','completed') DEFAULT 'draft',

created_by INT UNSIGNED NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

-- العلاقات
FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
FOREIGN KEY (currency_id) REFERENCES currencies(id),
FOREIGN KEY (created_by) REFERENCES users(id)
);
//////////////////
CREATE TABLE ad_translations (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

ad_id BIGINT UNSIGNED NOT NULL,
language_code VARCHAR(8) NOT NULL,

title VARCHAR(255),
description TEXT,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
FOREIGN KEY (language_code) REFERENCES languages(code),

UNIQUE KEY uniq_ad_lang (ad_id, language_code)
);
///////////////////
CREATE TABLE ad_translations (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

ad_id BIGINT UNSIGNED NOT NULL,
language_code VARCHAR(8) NOT NULL,

title VARCHAR(255),
description TEXT,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
FOREIGN KEY (language_code) REFERENCES languages(code),

UNIQUE KEY uniq_ad_lang (ad_id, language_code)
);
///////////////
CREATE TABLE ad_placement_items (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

placement_id BIGINT UNSIGNED NOT NULL,
ad_id BIGINT UNSIGNED NOT NULL,

priority INT DEFAULT 1,
weight INT DEFAULT 1, -- للـ rotation

start_date DATETIME NULL,
end_date DATETIME NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,

UNIQUE KEY uniq_ad_placement (placement_id, ad_id)
);
////////////////
CREATE TABLE ad_placement_items (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

placement_id BIGINT UNSIGNED NOT NULL,
ad_id BIGINT UNSIGNED NOT NULL,

priority INT DEFAULT 1,
weight INT DEFAULT 1, -- للـ rotation

start_date DATETIME NULL,
end_date DATETIME NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,

UNIQUE KEY uniq_ad_placement (placement_id, ad_id)
);
//////////////
CREATE TABLE ad_payments (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

campaign_id BIGINT UNSIGNED NOT NULL,

amount DECIMAL(12,2),
currency_id SMALLINT UNSIGNED NOT NULL,

status ENUM('pending','paid','failed') DEFAULT 'pending',

paid_at DATETIME NULL,

created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id),
FOREIGN KEY (currency_id) REFERENCES currencies(id)
);
