-- ════════════════════════════════════════════════════════════════════
--  Heliora Consulting — tracking & attribution migration
--  Date: 9 August 2026
--  Closes the P0 measurement gate in the H2 2026 Meta Campaign Strategy
--  (Section 10, tracking and attribution architecture).
--
--  Safe to run more than once: every statement is guarded, so re-running
--  it will not error and will not touch existing data.
--
--  HOW TO RUN
--    cPanel > phpMyAdmin > select the heliora_leads database > SQL tab
--    > paste this file > Go.
-- ════════════════════════════════════════════════════════════════════

-- ── Add columns only if they are not already present ─────────────────
-- MySQL has no "ADD COLUMN IF NOT EXISTS" before 8.0.29 / MariaDB 10.0,
-- so each column goes through a small guarded procedure.

DROP PROCEDURE IF EXISTS heliora_add_col;
DELIMITER //
CREATE PROCEDURE heliora_add_col(
  IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', ddl);
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

-- ── Deduplication keys ───────────────────────────────────────────────
-- lead_uid  : opaque public identifier. Safe to send to Meta, GA4 and the
--             CRM. Never expose the auto-increment id externally.
-- event_id  : shared by the browser Lead event and the CAPI Lead event so
--             Meta counts them once. One per accepted submission.
CALL heliora_add_col('leads','lead_uid',  "CHAR(32) DEFAULT NULL");
CALL heliora_add_col('leads','event_id',  "CHAR(36) DEFAULT NULL");

-- ── Full campaign attribution ────────────────────────────────────────
CALL heliora_add_col('leads','utm_content',      "VARCHAR(255) DEFAULT NULL");
CALL heliora_add_col('leads','utm_term',         "VARCHAR(255) DEFAULT NULL");
CALL heliora_add_col('leads','meta_campaign_id', "VARCHAR(64)  DEFAULT NULL");
CALL heliora_add_col('leads','meta_adset_id',    "VARCHAR(64)  DEFAULT NULL");
CALL heliora_add_col('leads','meta_ad_id',       "VARCHAR(64)  DEFAULT NULL");
CALL heliora_add_col('leads','placement',        "VARCHAR(100) DEFAULT NULL");
CALL heliora_add_col('leads','site_source_name', "VARCHAR(100) DEFAULT NULL");

-- ── Click and browser identifiers ────────────────────────────────────
-- fbp/fbc materially improve CAPI match quality. gclid and li_fat_id are
-- captured for the other channels already tagged on the site.
CALL heliora_add_col('leads','fbp',       "VARCHAR(128) DEFAULT NULL");
CALL heliora_add_col('leads','fbc',       "VARCHAR(255) DEFAULT NULL");
CALL heliora_add_col('leads','gclid',     "VARCHAR(255) DEFAULT NULL");
CALL heliora_add_col('leads','li_fat_id', "VARCHAR(255) DEFAULT NULL");

-- ── Consent and delivery status ──────────────────────────────────────
-- consent_state records what the visitor chose at the moment of submit.
-- Leads captured under 'declined' must never be sent to ad platforms.
CALL heliora_add_col('leads','consent_state', "VARCHAR(20)  DEFAULT NULL");
CALL heliora_add_col('leads','capi_status',   "VARCHAR(20)  DEFAULT NULL");
CALL heliora_add_col('leads','capi_response', "TEXT         DEFAULT NULL");

DROP PROCEDURE IF EXISTS heliora_add_col;

-- ── Indexes (guarded the same way) ───────────────────────────────────
DROP PROCEDURE IF EXISTS heliora_add_idx;
DELIMITER //
CREATE PROCEDURE heliora_add_idx(
  IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = idx
  ) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ADD INDEX `', idx, '` (', cols, ')');
    PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

CALL heliora_add_idx('leads','idx_lead_uid',    '`lead_uid`');
CALL heliora_add_idx('leads','idx_event_id',    '`event_id`');
CALL heliora_add_idx('leads','idx_meta_ad_id',  '`meta_ad_id`');
CALL heliora_add_idx('leads','idx_capi_status', '`capi_status`');

DROP PROCEDURE IF EXISTS heliora_add_idx;

-- ── Verify ───────────────────────────────────────────────────────────
-- Expect 16 rows. If any are missing, the migration did not complete.
SELECT COLUMN_NAME, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'leads'
  AND COLUMN_NAME IN (
    'lead_uid','event_id','utm_content','utm_term','meta_campaign_id',
    'meta_adset_id','meta_ad_id','placement','site_source_name','fbp','fbc',
    'gclid','li_fat_id','consent_state','capi_status','capi_response')
ORDER BY COLUMN_NAME;
