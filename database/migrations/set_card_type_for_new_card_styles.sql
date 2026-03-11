-- database/migrations/set_card_type_for_new_card_styles.sql
-- SUPERSEDED by: database/migrations/add_auction_notification_card_styles.sql
--
-- That migration ALTERs the card_type ENUM to include the new values,
-- inserts default rows, and also runs these UPDATE statements.
-- This file is kept for reference only and may be safely skipped if
-- add_auction_notification_card_styles.sql has already been run.

UPDATE `card_styles`
SET    `card_type` = 'jobs'
WHERE  `slug` = 'jobs-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'notification'
WHERE  `slug` = 'notification-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'auction'
WHERE  `slug` = 'auction-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'discount'
WHERE  `slug` = 'discount-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'plan'
WHERE  `slug` = 'plan-default'
  AND (`card_type` IS NULL OR `card_type` = '');

