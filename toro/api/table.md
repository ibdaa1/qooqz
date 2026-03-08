


-- ============================================================
-- 1. LANGUAGES
-- ============================================================
CREATE TABLE `languages` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `code`       VARCHAR(10)     NOT NULL COMMENT 'e.g. en, ar, fr, de',
  `name`       VARCHAR(100)    NOT NULL COMMENT 'English',
  `native`     VARCHAR(100)    NOT NULL COMMENT 'العربية',
  `direction`  ENUM('ltr','rtl') NOT NULL DEFAULT 'ltr',
  `flag_icon`  VARCHAR(100)    DEFAULT NULL,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `is_default` TINYINT(1)      NOT NULL DEFAULT 0,
  `sort_order` SMALLINT        NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lang_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Supported UI languages';

INSERT INTO `languages` (`code`,`name`,`native`,`direction`,`is_active`,`is_default`,`sort_order`) VALUES
  ('ar','Arabic','العربية','rtl',1,1,1),
  ('en','English','English','ltr',1,0,2),
  ('fr','French','Français','ltr',1,0,3),
  ('de','German','Deutsch','ltr',1,0,4),
  ('zh','Chinese','中文','ltr',1,0,5);


-- ============================================================
-- 2. TRANSLATION KEYS  (global i18n dictionary)
-- ============================================================
CREATE TABLE `translation_keys` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `key_name`   VARCHAR(200)    NOT NULL COMMENT 'dot.notation key e.g. nav.home',
  `context`    VARCHAR(100)    DEFAULT NULL COMMENT 'ui | email | sms',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `translation_values` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key_id`      INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `value`       TEXT          NOT NULL,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key_lang` (`key_id`,`language_id`),
  CONSTRAINT `fk_tv_key`  FOREIGN KEY (`key_id`)      REFERENCES `translation_keys`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tv_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 3. SETTINGS  (site-wide config — no translation needed)
-- ============================================================
CREATE TABLE `settings` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `group`       VARCHAR(80)   NOT NULL DEFAULT 'general' COMMENT 'general|seo|mail|payment|social',
  `key`         VARCHAR(120)  NOT NULL,
  `value`       TEXT          DEFAULT NULL,
  `type`        ENUM('text','number','boolean','json','color','image') NOT NULL DEFAULT 'text',
  `is_public`   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = exposed to frontend JS',
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`group`,`key`,`value`,`type`,`is_public`) VALUES
  ('general','site_name','TORO','text',1),
  ('general','logo','/assets/images/logo.png','image',1),
  ('general','favicon','/assets/images/favicon.ico','image',1),
  ('general','default_language','ar','text',1),
  ('general','timezone','Asia/Riyadh','text',0),
  ('general','currency_code','SAR','text',1),
  ('general','currency_symbol','﷼','text',1),
  ('general','tax_rate','15','number',0),
  ('seo','meta_title','TORO — عطور فاخرة','text',1),
  ('seo','meta_description','متجر تورو للعطور الفاخرة','text',1),
  ('social','facebook_url','','text',1),
  ('social','instagram_url','','text',1),
  ('social','twitter_url','','text',1),
  ('payment','cash_enabled','1','boolean',0),
  ('payment','card_enabled','1','boolean',0),
  ('payment','apple_pay_enabled','1','boolean',0),
  ('payment','stripe_public_key','','text',0),
  ('payment','stripe_secret_key','','text',0);


-- ============================================================
-- 4. THEME — COLORS & SIZES  (dynamic CSS variables)
-- ============================================================
CREATE TABLE `theme_colors` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `variable`    VARCHAR(80)   NOT NULL COMMENT 'CSS var name e.g. --primary',
  `value`       VARCHAR(30)   NOT NULL COMMENT '#hex or rgba()',
  `label`       VARCHAR(80)   DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_color_var` (`variable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `theme_colors` (`variable`,`value`,`label`) VALUES
  ('--primary','#B8860B','Gold'),
  ('--secondary','#1A1A1A','Dark'),
  ('--accent','#F5F0E8','Cream'),
  ('--text-main','#2C2C2C','Main Text'),
  ('--text-light','#888888','Light Text'),
  ('--bg-main','#FFFFFF','Background'),
  ('--bg-dark','#0D0D0D','Dark Background'),
  ('--border','#E0D5C5','Border'),
  ('--success','#28A745','Success'),
  ('--danger','#DC3545','Danger'),
  ('--warning','#FFC107','Warning');

CREATE TABLE `theme_sizes` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(30)   NOT NULL COMMENT 'XS | S | M | L | XL',
  `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_size_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `theme_sizes` (`name`,`sort_order`) VALUES
  ('25ml',1),('30ml',2),('50ml',3),('75ml',4),
  ('100ml',5),('150ml',6),('200ml',7);


-- ============================================================
-- 5. ROLES & PERMISSIONS
-- ============================================================
CREATE TABLE `roles` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(60)   NOT NULL,
  `slug`        VARCHAR(60)   NOT NULL,
  `description` VARCHAR(200)  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`name`,`slug`,`description`) VALUES
  ('Super Admin','super_admin','Full access'),
  ('Admin','admin','Store management'),
  ('Editor','editor','Content only'),
  ('Customer','customer','Registered buyer');

CREATE TABLE `permissions` (
  `id`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`   VARCHAR(100)  NOT NULL,
  `slug`   VARCHAR(100)  NOT NULL,
  `group`  VARCHAR(60)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id`       INT UNSIGNED  NOT NULL,
  `permission_id` INT UNSIGNED  NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 6. USERS
-- ============================================================
CREATE TABLE `users` (
  `id`                 INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `role_id`            INT UNSIGNED   NOT NULL DEFAULT 4 COMMENT '4=customer',
  `first_name`         VARCHAR(80)    NOT NULL,
  `last_name`          VARCHAR(80)    NOT NULL,
  `email`              VARCHAR(180)   NOT NULL,
  `email_verified_at`  TIMESTAMP      DEFAULT NULL,
  `phone`              VARCHAR(30)    DEFAULT NULL,
  `phone_verified_at`  TIMESTAMP      DEFAULT NULL,
  `password_hash`      VARCHAR(255)   DEFAULT NULL COMMENT 'NULL for OAuth-only users',
  `avatar`             VARCHAR(255)   DEFAULT NULL,
  `language_id`        INT UNSIGNED   DEFAULT NULL,
  `is_active`          TINYINT(1)     NOT NULL DEFAULT 1,
  `last_login_at`      TIMESTAMP      DEFAULT NULL,
  `remember_token`     VARCHAR(100)   DEFAULT NULL,
  `created_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`         TIMESTAMP      DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  KEY `idx_user_role` (`role_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`)     REFERENCES `roles`(`id`),
  CONSTRAINT `fk_user_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social OAuth providers
CREATE TABLE `user_social_accounts` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED  NOT NULL,
  `provider`     ENUM('google','facebook','apple') NOT NULL,
  `provider_uid` VARCHAR(200)  NOT NULL,
  `token`        TEXT          DEFAULT NULL,
  `refresh_token`TEXT          DEFAULT NULL,
  `expires_at`   TIMESTAMP     DEFAULT NULL,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social` (`provider`,`provider_uid`),
  CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User addresses
CREATE TABLE `user_addresses` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `label`         VARCHAR(60)   DEFAULT 'Home',
  `full_name`     VARCHAR(120)  NOT NULL,
  `phone`         VARCHAR(30)   NOT NULL,
  `country_code`  CHAR(2)       NOT NULL DEFAULT 'SA',
  `city`          VARCHAR(100)  NOT NULL,
  `district`      VARCHAR(100)  DEFAULT NULL,
  `address_line1` VARCHAR(200)  NOT NULL,
  `address_line2` VARCHAR(200)  DEFAULT NULL,
  `postal_code`   VARCHAR(20)   DEFAULT NULL,
  `is_default`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addr_user` (`user_id`),
  CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- JWT / refresh tokens
CREATE TABLE `user_tokens` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `token_hash` VARCHAR(255)  NOT NULL,
  `type`       ENUM('refresh','reset_password','verify_email','verify_phone') NOT NULL,
  `expires_at` TIMESTAMP     NOT NULL,
  `used_at`    TIMESTAMP     DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_user` (`user_id`),
  CONSTRAINT `fk_tok_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 7. CATEGORIES  (+translations)
-- ============================================================
CREATE TABLE `categories` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `parent_id`   INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = top level',
  `slug`        VARCHAR(120)  NOT NULL,
  `image`       VARCHAR(255)  DEFAULT NULL,
  `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`),
  KEY `idx_cat_parent` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `category_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `name`        VARCHAR(200)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  `meta_title`  VARCHAR(200)  DEFAULT NULL,
  `meta_desc`   VARCHAR(300)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_trans` (`category_id`,`language_id`),
  CONSTRAINT `fk_catt_cat`  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_catt_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 8. BRANDS  (+translations)
-- ============================================================
CREATE TABLE `brands` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(120)  NOT NULL,
  `logo`       VARCHAR(255)  DEFAULT NULL,
  `website`    VARCHAR(255)  DEFAULT NULL,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brands` (`slug`,`is_active`,`sort_order`) VALUES ('toro',1,1);

CREATE TABLE `brand_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `brand_id`    INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `name`        VARCHAR(200)  NOT NULL,
  `description` TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_brand_trans` (`brand_id`,`language_id`),
  CONSTRAINT `fk_brandt_brand` FOREIGN KEY (`brand_id`)    REFERENCES `brands`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_brandt_lang`  FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `brand_translations` (`brand_id`,`language_id`,`name`,`description`) VALUES
  (1,1,'تورو','عطور تورو الفاخرة'),
  (1,2,'TORO','TORO Luxury Fragrances');


-- ============================================================
-- 9. ATTRIBUTES & ATTRIBUTE VALUES  (+translations)
--    (gender, concentration, fragrance family, etc.)
-- ============================================================
CREATE TABLE `attributes` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(80)   NOT NULL,
  `type`       ENUM('select','multiselect','color','size','boolean') NOT NULL DEFAULT 'select',
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attr_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `attributes` (`slug`,`type`,`sort_order`) VALUES
  ('gender','select',1),
  ('concentration','select',2),
  ('fragrance_family','select',3),
  ('top_notes','multiselect',4),
  ('heart_notes','multiselect',5),
  ('base_notes','multiselect',6);

CREATE TABLE `attribute_translations` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `attribute_id` INT UNSIGNED  NOT NULL,
  `language_id`  INT UNSIGNED  NOT NULL,
  `name`         VARCHAR(200)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attrt` (`attribute_id`,`language_id`),
  CONSTRAINT `fk_attrt_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attrt_lang` FOREIGN KEY (`language_id`)  REFERENCES `languages`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attribute_values` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `attribute_id` INT UNSIGNED  NOT NULL,
  `slug`         VARCHAR(80)   NOT NULL,
  `color_hex`    VARCHAR(10)   DEFAULT NULL COMMENT 'for type=color',
  `sort_order`   SMALLINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_av_attr` (`attribute_id`),
  CONSTRAINT `fk_av_attr` FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attribute_value_translations` (
  `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `value_id` INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED NOT NULL,
  `name`     VARCHAR(200)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_avt` (`value_id`,`language_id`),
  CONSTRAINT `fk_avt_val`  FOREIGN KEY (`value_id`)    REFERENCES `attribute_values`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avt_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 10. PRODUCTS  (+translations)
-- ============================================================
CREATE TABLE `products` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `sku`          VARCHAR(80)     NOT NULL,
  `brand_id`     INT UNSIGNED    NOT NULL,
  `category_id`  INT UNSIGNED    DEFAULT NULL,
  `type`         ENUM('simple','variable','bundle') NOT NULL DEFAULT 'simple',
  `base_price`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `sale_price`   DECIMAL(10,2)   DEFAULT NULL,
  `stock_qty`    INT             NOT NULL DEFAULT 0,
  `weight_grams` SMALLINT UNSIGNED DEFAULT NULL,
  `thumbnail`    VARCHAR(255)    DEFAULT NULL,
  `is_featured`  TINYINT(1)      NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`   SMALLINT        NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_sku` (`sku`),
  KEY `idx_prod_brand`    (`brand_id`),
  KEY `idx_prod_category` (`category_id`),
  CONSTRAINT `fk_prod_brand`    FOREIGN KEY (`brand_id`)    REFERENCES `brands`(`id`),
  CONSTRAINT `fk_prod_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_translations` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id`     INT UNSIGNED  NOT NULL,
  `language_id`    INT UNSIGNED  NOT NULL,
  `name`           VARCHAR(300)  NOT NULL,
  `short_desc`     VARCHAR(500)  DEFAULT NULL,
  `description`    LONGTEXT      DEFAULT NULL,
  `ingredients`    TEXT          DEFAULT NULL,
  `how_to_use`     TEXT          DEFAULT NULL,
  `meta_title`     VARCHAR(200)  DEFAULT NULL,
  `meta_desc`      VARCHAR(300)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prod_trans` (`product_id`,`language_id`),
  CONSTRAINT `fk_pt_prod` FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_pt_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images gallery
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  NOT NULL,
  `url`        VARCHAR(255)  NOT NULL,
  `alt_text`   VARCHAR(200)  DEFAULT NULL,
  `sort_order` SMALLINT      NOT NULL DEFAULT 0,
  `is_cover`   TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pimg_prod` (`product_id`),
  CONSTRAINT `fk_pimg_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product ↔ attribute values  (e.g. gender=unisex, family=woody)
CREATE TABLE `product_attribute_values` (
  `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `value_id`   INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pav` (`product_id`,`value_id`),
  CONSTRAINT `fk_pav_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_pav_val`  FOREIGN KEY (`value_id`)   REFERENCES `attribute_values`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product variants (e.g. 50ml / 100ml with individual price & stock)
CREATE TABLE `product_variants` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED    NOT NULL,
  `size_id`    INT UNSIGNED    NOT NULL COMMENT 'references theme_sizes',
  `sku`        VARCHAR(80)     NOT NULL,
  `price`      DECIMAL(10,2)   NOT NULL,
  `sale_price` DECIMAL(10,2)   DEFAULT NULL,
  `stock_qty`  INT             NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variant_sku` (`sku`),
  KEY `idx_var_prod` (`product_id`),
  CONSTRAINT `fk_var_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_var_size` FOREIGN KEY (`size_id`)    REFERENCES `theme_sizes`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product reviews
CREATE TABLE `product_reviews` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED  NOT NULL,
  `user_id`    INT UNSIGNED  NOT NULL,
  `rating`     TINYINT       NOT NULL COMMENT '1–5',
  `title`      VARCHAR(200)  DEFAULT NULL,
  `body`       TEXT          DEFAULT NULL,
  `is_approved`TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rev_prod` (`product_id`),
  KEY `idx_rev_user` (`user_id`),
  CONSTRAINT `fk_rev_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wishlist
CREATE TABLE `wishlists` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED  NOT NULL,
  `product_id` INT UNSIGNED  NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wish` (`user_id`,`product_id`),
  CONSTRAINT `fk_wish_user` FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_wish_prod` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 11. MENUS  (+translations)
-- ============================================================
CREATE TABLE `menus` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(80)   NOT NULL COMMENT 'main_nav | footer | mobile',
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menu_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `menus` (`slug`) VALUES ('main_nav'),('footer'),('mobile_bottom');

CREATE TABLE `menu_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `menu_id`     INT UNSIGNED  NOT NULL,
  `parent_id`   INT UNSIGNED  DEFAULT NULL,
  `type`        ENUM('link','category','product','page','custom') NOT NULL DEFAULT 'link',
  `reference_id`INT UNSIGNED  DEFAULT NULL COMMENT 'category_id / product_id / page_id',
  `url`         VARCHAR(255)  DEFAULT NULL,
  `icon`        VARCHAR(100)  DEFAULT NULL,
  `target`      ENUM('_self','_blank') NOT NULL DEFAULT '_self',
  `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_mi_menu`   (`menu_id`),
  KEY `idx_mi_parent` (`parent_id`),
  CONSTRAINT `fk_mi_menu`   FOREIGN KEY (`menu_id`)   REFERENCES `menus`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_mi_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_item_translations` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `menu_item_id` INT UNSIGNED  NOT NULL,
  `language_id`  INT UNSIGNED  NOT NULL,
  `label`        VARCHAR(200)  NOT NULL,
  `tooltip`      VARCHAR(200)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mit` (`menu_item_id`,`language_id`),
  CONSTRAINT `fk_mit_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mit_lang` FOREIGN KEY (`language_id`)  REFERENCES `languages`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 12. BANNERS / SLIDERS  (+translations)
-- ============================================================
CREATE TABLE `banners` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `position`    VARCHAR(60)   NOT NULL COMMENT 'hero | promo | sidebar',
  `image`       VARCHAR(255)  NOT NULL,
  `mobile_image`VARCHAR(255)  DEFAULT NULL,
  `link_url`    VARCHAR(255)  DEFAULT NULL,
  `sort_order`  SMALLINT      NOT NULL DEFAULT 0,
  `starts_at`   TIMESTAMP     DEFAULT NULL,
  `ends_at`     TIMESTAMP     DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `banner_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `banner_id`   INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `title`       VARCHAR(200)  DEFAULT NULL,
  `subtitle`    VARCHAR(300)  DEFAULT NULL,
  `cta_text`    VARCHAR(80)   DEFAULT NULL,
  `alt_text`    VARCHAR(200)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_banner_trans` (`banner_id`,`language_id`),
  CONSTRAINT `fk_bt_banner` FOREIGN KEY (`banner_id`)   REFERENCES `banners`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_bt_lang`   FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 13. STATIC PAGES  (+translations)
-- ============================================================
CREATE TABLE `pages` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(120)  NOT NULL,
  `template`   VARCHAR(60)   DEFAULT 'default',
  `is_active`  TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pages` (`slug`) VALUES ('about'),('contact'),('privacy-policy'),('terms');

CREATE TABLE `page_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `page_id`     INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `title`       VARCHAR(300)  NOT NULL,
  `content`     LONGTEXT      NOT NULL,
  `meta_title`  VARCHAR(200)  DEFAULT NULL,
  `meta_desc`   VARCHAR(300)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_trans` (`page_id`,`language_id`),
  CONSTRAINT `fk_pgt_page` FOREIGN KEY (`page_id`)     REFERENCES `pages`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_pgt_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 14. COUPONS & PROMOTIONS
-- ============================================================
CREATE TABLE `coupons` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(50)     NOT NULL,
  `type`             ENUM('percent','fixed','free_shipping') NOT NULL DEFAULT 'percent',
  `value`            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `min_order_amount` DECIMAL(10,2)   DEFAULT NULL,
  `max_uses`         INT             DEFAULT NULL COMMENT 'NULL = unlimited',
  `uses_count`       INT             NOT NULL DEFAULT 0,
  `starts_at`        TIMESTAMP       DEFAULT NULL,
  `expires_at`       TIMESTAMP       DEFAULT NULL,
  `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `coupon_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `coupon_id`   INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `description` VARCHAR(300)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ct` (`coupon_id`,`language_id`),
  CONSTRAINT `fk_ct_coupon` FOREIGN KEY (`coupon_id`)   REFERENCES `coupons`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_ct_lang`   FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 15. CARTS
-- ============================================================
CREATE TABLE `carts` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = guest',
  `session_key` VARCHAR(100)  DEFAULT NULL,
  `coupon_id`   INT UNSIGNED  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_user`    (`user_id`),
  KEY `idx_cart_session` (`session_key`),
  CONSTRAINT `fk_cart_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_cart_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `cart_id`    INT UNSIGNED    NOT NULL,
  `product_id` INT UNSIGNED    NOT NULL,
  `variant_id` INT UNSIGNED    DEFAULT NULL,
  `qty`        SMALLINT        NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2)   NOT NULL COMMENT 'snapshot at add time',
  `added_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ci` (`cart_id`,`product_id`,`variant_id`),
  CONSTRAINT `fk_ci_cart`    FOREIGN KEY (`cart_id`)    REFERENCES `carts`(`id`)            ON DELETE CASCADE,
  CONSTRAINT `fk_ci_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_ci_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 16. ORDERS
-- ============================================================
CREATE TABLE `orders` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_number`    VARCHAR(30)     NOT NULL,
  `user_id`         INT UNSIGNED    DEFAULT NULL,
  `address_id`      INT UNSIGNED    DEFAULT NULL,
  `status`          ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded')
                    NOT NULL DEFAULT 'pending',
  `subtotal`        DECIMAL(10,2)   NOT NULL,
  `discount`        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `shipping_cost`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `tax`             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(10,2)   NOT NULL,
  `currency`        CHAR(3)         NOT NULL DEFAULT 'SAR',
  `coupon_id`       INT UNSIGNED    DEFAULT NULL,
  `notes`           TEXT            DEFAULT NULL,
  `ip_address`      VARCHAR(45)     DEFAULT NULL,
  `user_agent`      VARCHAR(300)    DEFAULT NULL,
  `language_id`     INT UNSIGNED    DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`),
  KEY `idx_ord_user`   (`user_id`),
  KEY `idx_ord_status` (`status`),
  CONSTRAINT `fk_ord_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_ord_addr`   FOREIGN KEY (`address_id`)REFERENCES `user_addresses`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ord_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED    NOT NULL,
  `product_id`   INT UNSIGNED    NOT NULL,
  `variant_id`   INT UNSIGNED    DEFAULT NULL,
  `product_name` VARCHAR(300)    NOT NULL COMMENT 'snapshot',
  `sku`          VARCHAR(80)     NOT NULL COMMENT 'snapshot',
  `qty`          SMALLINT        NOT NULL DEFAULT 1,
  `unit_price`   DECIMAL(10,2)   NOT NULL,
  `discount`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `total`        DECIMAL(10,2)   NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`(`id`)           ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)         ON DELETE RESTRICT,
  CONSTRAINT `fk_oi_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order status history log
CREATE TABLE `order_status_history` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED  NOT NULL,
  `status`     VARCHAR(50)   NOT NULL,
  `note`       TEXT          DEFAULT NULL,
  `created_by` INT UNSIGNED  DEFAULT NULL COMMENT 'admin user id',
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_osh_order` (`order_id`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping snapshots (denormalised for legal record)
CREATE TABLE `order_shipping_addresses` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id`     INT UNSIGNED  NOT NULL,
  `full_name`    VARCHAR(120)  NOT NULL,
  `phone`        VARCHAR(30)   NOT NULL,
  `country_code` CHAR(2)       NOT NULL,
  `city`         VARCHAR(100)  NOT NULL,
  `district`     VARCHAR(100)  DEFAULT NULL,
  `address_line1`VARCHAR(200)  NOT NULL,
  `address_line2`VARCHAR(200)  DEFAULT NULL,
  `postal_code`  VARCHAR(20)   DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_osa_order` (`order_id`),
  CONSTRAINT `fk_osa_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 17. PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
  `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_id`       INT UNSIGNED    NOT NULL,
  `method`         ENUM('cash','card','apple_pay') NOT NULL,
  `status`         ENUM('pending','paid','failed','refunded','partially_refunded')
                   NOT NULL DEFAULT 'pending',
  `amount`         DECIMAL(10,2)   NOT NULL,
  `currency`       CHAR(3)         NOT NULL DEFAULT 'SAR',
  `gateway`        VARCHAR(60)     DEFAULT NULL COMMENT 'stripe | tap | moyasar',
  `gateway_txn_id` VARCHAR(200)    DEFAULT NULL,
  `gateway_ref`    VARCHAR(200)    DEFAULT NULL,
  `gateway_resp`   JSON            DEFAULT NULL COMMENT 'raw gateway payload',
  `paid_at`        TIMESTAMP       DEFAULT NULL,
  `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_order` (`order_id`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds
CREATE TABLE `refunds` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `payment_id`  INT UNSIGNED    NOT NULL,
  `amount`      DECIMAL(10,2)   NOT NULL,
  `reason`      TEXT            DEFAULT NULL,
  `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `processed_by`INT UNSIGNED    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ref_pay` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 18. NOTIFICATIONS  (multi-channel, translatable content)
-- ============================================================
CREATE TABLE `notification_templates` (
  `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`     VARCHAR(100)  NOT NULL COMMENT 'order_confirmed | welcome | etc.',
  `channel`  ENUM('email','sms','push') NOT NULL,
  `is_active`TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_slug_chan` (`slug`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_template_translations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `template_id` INT UNSIGNED  NOT NULL,
  `language_id` INT UNSIGNED  NOT NULL,
  `subject`     VARCHAR(300)  DEFAULT NULL,
  `body`        LONGTEXT      NOT NULL COMMENT 'supports {{variable}} placeholders',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ntt` (`template_id`,`language_id`),
  CONSTRAINT `fk_ntt_tmpl` FOREIGN KEY (`template_id`) REFERENCES `notification_templates`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ntt_lang` FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sent notifications log
CREATE TABLE `notifications_log` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  DEFAULT NULL,
  `template_id` INT UNSIGNED  DEFAULT NULL,
  `channel`     ENUM('email','sms','push') NOT NULL,
  `recipient`   VARCHAR(200)  NOT NULL,
  `status`      ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `sent_at`     TIMESTAMP     DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nlog_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 19. AUDIT LOG  (security & activity trail)
-- ============================================================
CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL COMMENT 'create | update | delete | login | etc.',
  `entity`      VARCHAR(80)     DEFAULT NULL COMMENT 'table name',
  `entity_id`   INT UNSIGNED    DEFAULT NULL,
  `old_values`  JSON            DEFAULT NULL,
  `new_values`  JSON            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(300)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alog_user`   (`user_id`),
  KEY `idx_alog_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 20. CSRF TOKENS  (server-side store)
-- ============================================================
CREATE TABLE `csrf_tokens` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `token`      VARCHAR(128)  NOT NULL,
  `session_id` VARCHAR(128)  NOT NULL,
  `expires_at` TIMESTAMP     NOT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_csrf` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 21. RATE LIMITING
-- ============================================================
CREATE TABLE `rate_limits` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key`        VARCHAR(200)  NOT NULL COMMENT 'ip:action or user_id:action',
  `attempts`   SMALLINT      NOT NULL DEFAULT 1,
  `blocked_until` TIMESTAMP  DEFAULT NULL,
  `last_attempt`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rl_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ENABLE FK CHECKS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- SUMMARY VIEW: all translatable tables at a glance
-- ============================================================
/*
  ┌─────────────────────────────────────┬───────────────────────────────────────┐
  │  Entity Table                       │  Translation Table                    │
  ├─────────────────────────────────────┼───────────────────────────────────────┤
  │  languages                          │  — (base table)                       │
  │  translation_keys                   │  translation_values                   │
  │  categories                         │  category_translations                │
  │  brands                             │  brand_translations                   │
  │  attributes                         │  attribute_translations               │
  │  attribute_values                   │  attribute_value_translations         │
  │  products                           │  product_translations                 │
  │  menus / menu_items                 │  menu_item_translations               │
  │  banners                            │  banner_translations                  │
  │  pages                              │  page_translations                    │
  │  coupons                            │  coupon_translations                  │
  │  notification_templates             │  notification_template_translations   │
  └─────────────────────────────────────┴───────────────────────────────────────┘

  Tables that do NOT need translations (raw data / config):
    settings, theme_colors, theme_sizes, roles, permissions,
    users, user_social_accounts, user_addresses, user_tokens,
    carts, cart_items, orders, order_items, order_status_history,
    order_shipping_addresses, payments, refunds,
    product_images, product_attribute_values, product_variants,
    product_reviews, wishlists, audit_logs, csrf_tokens, rate_limits
*/