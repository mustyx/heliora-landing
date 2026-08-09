-- ════════════════════════════════════════════════════════════════════
--  Heliora Consulting — lead qualification fields migration
--  Date: 9 August 2026
--  Closes the P1 form gap in the H2 2026 Meta Campaign Strategy v1.2
--  (Section 03, lead score; Section 11, landing-page audit P1 row 1;
--   Section 17, mandatory CRM fields).
--
--  WHY THESE THREE COLUMNS
--    The Section 03 scoring model is worth 100 points across seven
--    signals. Four of them were already captured (client type, project
--    scale, service fit, brief quality). Three were not:
--      project_stage     15 pts
--      decision_horizon  10 pts
--      authority         10 pts
--    Without them 35 of 100 points had to be guessed by hand at T+15min,
--    and the MQL/SQL thresholds (60 / 75) were being applied to a score
--    that could not reach 100. Capturing them at the form makes the score
--    computable, which is what the CRM scoring gate fix depends on.
--
--  Follows the rev-2 pattern of 2026-08-09-tracking.sql: ALTER and INDEX
--  rights on your own database only — no information_schema, no stored
--  procedures, both of which cPanel MySQL users are denied.
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
--  project_stage     : where the project actually is. Section 03 awards
--                      the full 15 only for feasibility, design,
--                      procurement, construction or an operating asset.
--                      'concept' and 'exploring' score zero by design —
--                      they are the nurture pool, not a failure state.
--  decision_horizon  : when engagement is needed. Section 03 awards 10
--                      only for "within six months".
--  authority         : the contact's relationship to the decision.
--                      Section 03 awards 10 for decision-maker, project
--                      owner, technical evaluator or mandated adviser.
--  qualification_required : whether the visitor was on the paid path and
--                      therefore saw the fields as mandatory. Stored so
--                      a blank value can be read as "organic visitor,
--                      never asked" rather than "paid lead, skipped it" —
--                      the two mean very different things when you are
--                      reading lead quality by campaign.

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `project_stage`          VARCHAR(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `decision_horizon`       VARCHAR(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `authority`              VARCHAR(32) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `qualification_required` TINYINT(1)  DEFAULT 0;


-- ════════════════════════════════════════════════════════════════════
--  STEP 2 — add the indexes
-- ════════════════════════════════════════════════════════════════════
--  Indexed because the Friday feedback loop in Section 17 slices leads
--  by these fields to compare ads on cost per MQL rather than CPL.

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_project_stage`    (`project_stage`),
  ADD INDEX IF NOT EXISTS `idx_decision_horizon` (`decision_horizon`),
  ADD INDEX IF NOT EXISTS `idx_authority`        (`authority`);


-- ════════════════════════════════════════════════════════════════════
--  STEP 3 — verify
-- ════════════════════════════════════════════════════════════════════
--  You should see the four new columns at the bottom of the list.

SHOW COLUMNS FROM `leads`;


-- ════════════════════════════════════════════════════════════════════
--  FALLBACK — only if STEP 1 or 2 fails with a SYNTAX error
-- ════════════════════════════════════════════════════════════════════
--  "IF NOT EXISTS" on ADD COLUMN is MariaDB syntax and worked for the
--  tracking migration on this host, so it should work here too. If the
--  host has since moved to stock MySQL it will reject the phrase with
--  "#1064 ... error in your SQL syntax near 'IF NOT EXISTS'".
--
--  In that case run the two statements below INSTEAD of steps 1 and 2.
--  They are identical minus the guard, so they can only be run once — a
--  second run reports "#1060 Duplicate column name", which is harmless
--  and simply means that column was already added.
--
--  ALTER TABLE `leads`
--    ADD COLUMN `project_stage`          VARCHAR(32) DEFAULT NULL,
--    ADD COLUMN `decision_horizon`       VARCHAR(32) DEFAULT NULL,
--    ADD COLUMN `authority`              VARCHAR(32) DEFAULT NULL,
--    ADD COLUMN `qualification_required` TINYINT(1)  DEFAULT 0;
--
--  ALTER TABLE `leads`
--    ADD INDEX `idx_project_stage`    (`project_stage`),
--    ADD INDEX `idx_decision_horizon` (`decision_horizon`),
--    ADD INDEX `idx_authority`        (`authority`);
-- ════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════
--  REFERENCE — accepted values
-- ════════════════════════════════════════════════════════════════════
--  These are whitelisted in submit-lead.php. Anything else is stored as
--  an empty string, never rejected, so a tampered or stale form can
--  never lose a real lead. Keep the three lists in sync: the form
--  options in index.html, the whitelists in submit-lead.php, and the
--  picklist values in the Zoho CRM custom fields.
--
--  project_stage      concept | feasibility | design | procurement
--                     | construction | operating | exploring
--  decision_horizon   immediate | within_3_months | within_6_months
--                     | 6_to_12_months | beyond_12_months | unsure
--  authority          decision_maker | project_owner | technical_evaluator
--                     | mandated_adviser | influencer | gathering_info
-- ════════════════════════════════════════════════════════════════════
