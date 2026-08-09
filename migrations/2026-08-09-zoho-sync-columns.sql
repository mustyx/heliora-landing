-- ════════════════════════════════════════════════════════════════════
--  Heliora Consulting — Zoho sync audit columns
--  Date: 9 August 2026
--
--  WHY THIS EXISTS
--    includes/zoho.php line ~134 runs, after every successful CRM push:
--
--      UPDATE leads SET zoho_lead_id=?, zoho_synced_at=NOW() WHERE id=?
--
--    Neither column exists in the live table. The statement is wrapped in a
--    try/catch that logs and continues, so it never breaks lead capture -
--    which is exactly why it would have gone unnoticed. The CRM record would
--    be created correctly and the local link to it silently thrown away.
--
--    Consequences without this migration, once ZOHO_ENABLED is true:
--      - No way to tell which leads reached the CRM and which did not.
--      - The admin detail panel reports "Not synced" for every lead,
--        including the ones that synced.
--      - A re-push cannot be detected, so a retry creates a duplicate CRM
--        record instead of recognising the lead is already there.
--
--  HOW THIS HAPPENED - worth knowing, because it will bite again
--    There are two schema files in this repo and they disagree:
--
--      database.sql   22 leads columns. Has project_budget, zoho_lead_id,
--                     zoho_synced_at. MISSING project_scale, client_type.
--      db-schema.sql  21 leads columns. Has project_scale, client_type.
--                     MISSING all three of the above.
--
--    The live table was built from db-schema.sql, so it inherited that file's
--    gaps. database.sql is the older file and is now actively misleading:
--    DEPLOY.md Step 2 still tells you to import it, which on a fresh install
--    would produce a table with no project_scale or client_type at all.
--
--    Treat db-schema.sql + the files in migrations/ as the truth.
--
--  RUN THIS BEFORE SETTING ZOHO_ENABLED=true.
--
--  HOW TO RUN
--    cPanel > phpMyAdmin > select prosuzec_heliora_leads > SQL tab
--    > paste STEP 1, press Go
--    > paste STEP 2, press Go
-- ════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════
--  STEP 1 — add the columns
-- ════════════════════════════════════════════════════════════════════
--  zoho_lead_id   : the id Zoho returns on create. The link between a row
--                   here and a record there. Indexed so a duplicate-push
--                   check is cheap.
--  zoho_synced_at : when the push succeeded. NULL means never synced, which
--                   is the honest state for every row created before the
--                   integration was switched on.

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `zoho_lead_id`   VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `zoho_synced_at` DATETIME    DEFAULT NULL;


-- ════════════════════════════════════════════════════════════════════
--  STEP 2 — index and verify
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_zoho_lead_id` (`zoho_lead_id`);

SHOW COLUMNS FROM `leads` LIKE 'zoho%';


-- ════════════════════════════════════════════════════════════════════
--  FALLBACK — only if the above fails with a SYNTAX error (#1064)
-- ════════════════════════════════════════════════════════════════════
--  "IF NOT EXISTS" on ADD COLUMN is MariaDB syntax and has worked on this
--  host for the previous two migrations. If the host has moved to stock
--  MySQL, run these instead. A second run reports "#1060 Duplicate column
--  name", which is harmless.
--
--  ALTER TABLE `leads`
--    ADD COLUMN `zoho_lead_id`   VARCHAR(50) DEFAULT NULL,
--    ADD COLUMN `zoho_synced_at` DATETIME    DEFAULT NULL;
--
--  ALTER TABLE `leads` ADD INDEX `idx_zoho_lead_id` (`zoho_lead_id`);
-- ════════════════════════════════════════════════════════════════════
