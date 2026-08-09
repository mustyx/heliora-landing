-- ════════════════════════════════════════════════════════════════════
--  Heliora Consulting — tracking & attribution migration
--  Date: 9 August 2026  (rev 2)
--  Closes the P0 measurement gate in the H2 2026 Meta Campaign Strategy
--  (Section 10, tracking and attribution architecture).
--
--  rev 2: rewritten for Namecheap shared hosting. The first version used
--  information_schema and stored procedures to make itself idempotent;
--  cPanel MySQL users are denied both, which produced
--  "#1044 Access denied for user ... to database 'information_schema'".
--  This version needs only ALTER and INDEX rights on your own database.
--
--  HOW TO RUN
--    cPanel > phpMyAdmin > select prosuzec_heliora_leads > SQL tab
--    > paste STEP 1, press Go
--    > paste STEP 2, press Go
--    > paste STEP 3, press Go
--    Run them one at a time so you can see which one fails, if any.
-- ════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════
--  STEP 1 — add the columns
-- ════════════════════════════════════════════════════════════════════
--  lead_uid  : opaque public identifier. Safe to send to Meta, GA4 and
--              the CRM, so the auto-increment id is never exposed.
--  event_id  : shared by the browser Lead event and the Conversions API
--              Lead event so Meta counts them once.

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `lead_uid`         CHAR(32)     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `event_id`         CHAR(36)     DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `utm_content`      VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `utm_term`         VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meta_campaign_id` VARCHAR(64)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meta_adset_id`    VARCHAR(64)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `meta_ad_id`       VARCHAR(64)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `placement`        VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `site_source_name` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fbp`              VARCHAR(128) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `fbc`              VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `gclid`            VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `li_fat_id`        VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `consent_state`    VARCHAR(20)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `capi_status`      VARCHAR(20)  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `capi_response`    TEXT         DEFAULT NULL;


-- ════════════════════════════════════════════════════════════════════
--  STEP 2 — add the indexes
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_lead_uid`    (`lead_uid`),
  ADD INDEX IF NOT EXISTS `idx_event_id`    (`event_id`),
  ADD INDEX IF NOT EXISTS `idx_meta_ad_id`  (`meta_ad_id`),
  ADD INDEX IF NOT EXISTS `idx_capi_status` (`capi_status`);


-- ════════════════════════════════════════════════════════════════════
--  STEP 3 — verify
-- ════════════════════════════════════════════════════════════════════
--  Needs no special privilege. You should see the original columns plus
--  the 16 new ones at the bottom of the list.

SHOW COLUMNS FROM `leads`;


-- ════════════════════════════════════════════════════════════════════
--  FALLBACK — only if STEP 1 or 2 fails with a SYNTAX error
-- ════════════════════════════════════════════════════════════════════
--  "IF NOT EXISTS" on ADD COLUMN is MariaDB syntax. Namecheap runs
--  MariaDB, so the statements above should work. If your host is running
--  stock MySQL instead, it will reject that phrase with something like
--  "#1064 You have an error in your SQL syntax near 'IF NOT EXISTS'".
--
--  In that case, run the two statements below INSTEAD of steps 1 and 2.
--  They are identical minus the guard, so they can only be run once —
--  a second run reports "#1060 Duplicate column name", which is harmless
--  and simply means that column was already added.
--
--  ALTER TABLE `leads`
--    ADD COLUMN `lead_uid`         CHAR(32)     DEFAULT NULL,
--    ADD COLUMN `event_id`         CHAR(36)     DEFAULT NULL,
--    ADD COLUMN `utm_content`      VARCHAR(255) DEFAULT NULL,
--    ADD COLUMN `utm_term`         VARCHAR(255) DEFAULT NULL,
--    ADD COLUMN `meta_campaign_id` VARCHAR(64)  DEFAULT NULL,
--    ADD COLUMN `meta_adset_id`    VARCHAR(64)  DEFAULT NULL,
--    ADD COLUMN `meta_ad_id`       VARCHAR(64)  DEFAULT NULL,
--    ADD COLUMN `placement`        VARCHAR(100) DEFAULT NULL,
--    ADD COLUMN `site_source_name` VARCHAR(100) DEFAULT NULL,
--    ADD COLUMN `fbp`              VARCHAR(128) DEFAULT NULL,
--    ADD COLUMN `fbc`              VARCHAR(255) DEFAULT NULL,
--    ADD COLUMN `gclid`            VARCHAR(255) DEFAULT NULL,
--    ADD COLUMN `li_fat_id`        VARCHAR(255) DEFAULT NULL,
--    ADD COLUMN `consent_state`    VARCHAR(20)  DEFAULT NULL,
--    ADD COLUMN `capi_status`      VARCHAR(20)  DEFAULT NULL,
--    ADD COLUMN `capi_response`    TEXT         DEFAULT NULL;
--
--  ALTER TABLE `leads`
--    ADD INDEX `idx_lead_uid`    (`lead_uid`),
--    ADD INDEX `idx_event_id`    (`event_id`),
--    ADD INDEX `idx_meta_ad_id`  (`meta_ad_id`),
--    ADD INDEX `idx_capi_status` (`capi_status`);
-- ════════════════════════════════════════════════════════════════════
