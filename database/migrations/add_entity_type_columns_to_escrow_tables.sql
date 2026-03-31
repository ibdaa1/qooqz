-- Add missing entity_type columns to escrow tables
-- These columns are required by the application code but were missing from the initial schema

-- escrow_transactions: add buyer_entity_type and seller_entity_type
ALTER TABLE escrow_transactions
    ADD COLUMN IF NOT EXISTS buyer_entity_type  VARCHAR(50) NOT NULL DEFAULT 'unknown' AFTER buyer_entity_id,
    ADD COLUMN IF NOT EXISTS seller_entity_type VARCHAR(50) NOT NULL DEFAULT 'unknown' AFTER seller_entity_id;

-- escrow_status_history: add changed_by_entity_type
ALTER TABLE escrow_status_history
    ADD COLUMN IF NOT EXISTS changed_by_entity_type VARCHAR(50) NULL AFTER changed_by_entity_id;

-- escrow_disputes: add raised_by_entity_type
ALTER TABLE escrow_disputes
    ADD COLUMN IF NOT EXISTS raised_by_entity_type VARCHAR(50) NOT NULL DEFAULT 'unknown' AFTER raised_by_entity_id;

-- escrow_dispute_evidence: add uploaded_by_entity_type
ALTER TABLE escrow_dispute_evidence
    ADD COLUMN IF NOT EXISTS uploaded_by_entity_type VARCHAR(50) NULL AFTER uploaded_by_entity_id;

-- escrow_ledger: add entity_type
ALTER TABLE escrow_ledger
    ADD COLUMN IF NOT EXISTS entity_type VARCHAR(50) NOT NULL DEFAULT 'unknown' AFTER entity_id;
