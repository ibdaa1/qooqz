-- Create contact_messages table
-- Stores messages submitted via the Contact Us form.
-- user_id is required — only logged-in users may submit.

CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    message     TEXT         NOT NULL,
    status      ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact_tenant   (tenant_id),
    INDEX idx_contact_user     (user_id),
    INDEX idx_contact_status   (status),
    INDEX idx_contact_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
