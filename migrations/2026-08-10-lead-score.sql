-- ════════════════════════════════════════════════════════════════════
--  Heliora Consulting — lead score and grade columns
--  Date: 10 August 2026
--
--  Stores the output of includes/lead-score.php, which implements the
--  Section 03 model with MQL/SQL gated on authority and decision horizon
--  rather than summed alongside everything else.
--
--  WHY STORE IT AT ALL, GIVEN ZOHO HOLDS IT TOO
--    Zoho is the sales system of record, but the Friday feedback loop in
--    Section 17 compares ads on cost per MQL, and that query needs score
--    and grade sitting next to meta_ad_id in one table. Reconstructing it
--    by joining CSV exports is exactly the friction that stops a weekly
--    review from happening. It also means a lead is still scored if the
--    CRM push fails.
--
--  lead_score    0-100. NULL only for rows created before this migration.
--  lead_grade    sql | mql | nurture | disqualify | unscored
--                'unscored' is not a low score - it means the visitor was
--                never asked (organic, lighter form). Filing those with
--                genuinely weak leads would teach the wrong lesson about
--                organic traffic.
--  score_reason  the human sentence shown in admin and sent to the CRM,
--                so a surprising grade can always be explained without
--                re-running the scorer.
--
--  HOW TO RUN
--    cPanel > phpMyAdmin > prosuzec_heliora_leads > SQL tab
--    > paste STEP 1, press Go   > paste STEP 2, press Go
--
--  Safe to run BEFORE deploying the code: the columns are simply unused
--  until the new submit-lead.php lands. Run it first, as always.
-- ════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════
--  STEP 1 — add the columns
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `leads`
  ADD COLUMN IF NOT EXISTS `lead_score`   TINYINT UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `lead_grade`   VARCHAR(12)      DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `score_reason` VARCHAR(255)     DEFAULT NULL;


-- ════════════════════════════════════════════════════════════════════
--  STEP 2 — index and verify
-- ════════════════════════════════════════════════════════════════════
--  Composite on (lead_grade, created_at) because every question worth
--  asking is "how many MQLs this week", not "list all MQLs ever".

ALTER TABLE `leads`
  ADD INDEX IF NOT EXISTS `idx_grade_created` (`lead_grade`, `created_at`);

--  Must return THREE rows. Do not use LIKE '%score%' here - it misses
--  lead_grade, which does not contain the string "score", and so reports a
--  successful migration as though a column were absent. That was the original
--  version of this line and it is exactly the kind of check that looks like
--  verification without being any.

SHOW COLUMNS FROM `leads` WHERE Field IN ('lead_score', 'lead_grade', 'score_reason');


-- ════════════════════════════════════════════════════════════════════
--  THE FRIDAY QUERY — Section 17's feedback loop, now one statement
-- ════════════════════════════════════════════════════════════════════
--  Compare ads on cost per MQL rather than CPL. Spend comes from Meta;
--  everything else is here.
--
--  SELECT meta_ad_id, utm_content,
--         COUNT(*)                                          AS leads,
--         SUM(lead_grade IN ('mql','sql'))                  AS qualified,
--         SUM(lead_grade = 'sql')                           AS sql_leads,
--         SUM(lead_grade = 'disqualify')                    AS junk,
--         SUM(lead_grade = 'unscored')                      AS not_asked,
--         ROUND(AVG(NULLIF(lead_score,0)), 1)               AS avg_score
--  FROM leads
--  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
--    AND meta_ad_id <> ''
--  GROUP BY meta_ad_id, utm_content
--  ORDER BY qualified DESC, leads DESC;
--
--  Read 'not_asked' before drawing conclusions: those are organic leads
--  the form never questioned, so they cannot count for or against an ad.
-- ════════════════════════════════════════════════════════════════════


-- ════════════════════════════════════════════════════════════════════
--  FALLBACK — only if STEP 1 or 2 fails with #1064
-- ════════════════════════════════════════════════════════════════════
--  ALTER TABLE `leads`
--    ADD COLUMN `lead_score`   TINYINT UNSIGNED DEFAULT NULL,
--    ADD COLUMN `lead_grade`   VARCHAR(12)      DEFAULT NULL,
--    ADD COLUMN `score_reason` VARCHAR(255)     DEFAULT NULL;
--
--  ALTER TABLE `leads` ADD INDEX `idx_grade_created` (`lead_grade`, `created_at`);
-- ════════════════════════════════════════════════════════════════════
