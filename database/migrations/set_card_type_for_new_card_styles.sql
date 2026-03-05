-- database/migrations/set_card_type_for_new_card_styles.sql
-- Sets the card_type column for card_styles rows that were inserted
-- without a card_type value (rows created 2026-03-05).
-- These card_type values align with what pub_card_inline_style() looks up:
--   'jobs'         used by jobs.php / job.php
--   'notification' used by notifications.php
--   'auction'      used by auctions.php / auction.php
--   'discount'     used by discounts.php / wishlist.php
--   'plan'         used by pricing plan pages

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
