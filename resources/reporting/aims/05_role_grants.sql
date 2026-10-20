/* Adds rpt_role_grant to a reporting database that was installed before role ids.
   Safe to re-run: the table is created if missing, and seed rows go in only when it is empty. */
USE `aims_rpt`;

CREATE TABLE IF NOT EXISTS rpt_role_access (
  role_name VARCHAR(191) NOT NULL PRIMARY KEY COMMENT 'Legacy display name. Not used for access.',
  scope ENUM('all','org','participant','none') NOT NULL DEFAULT 'none',
  can_dashboard TINYINT NOT NULL DEFAULT 0,
  can_cases TINYINT NOT NULL DEFAULT 0,
  can_audit TINYINT NOT NULL DEFAULT 0,
  can_export TINYINT NOT NULL DEFAULT 0,
  can_see_names TINYINT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rpt_role_access (role_name, scope, can_dashboard, can_cases, can_audit, can_export, can_see_names, note)
SELECT * FROM (
  SELECT 'Executive Management' AS role_name, 'all' AS scope, 1 AS d, 1 AS c, 1 AS a, 1 AS e, 1 AS n,
         'BRS Management (Director, Deputy Head): all cases, dashboard, audit log' AS note
  UNION ALL SELECT 'Operational Management', 'org', 1, 1, 0, 1, 1, 'BRS Management (Manager, Senior Manager): cases in the org units linked to them in AIMS'
  UNION ALL SELECT 'Investigator', 'participant', 0, 1, 0, 0, 1, 'BRS 4.6.3: reports on allocated cases only; no dashboard; no downloads'
  UNION ALL SELECT 'super-admin', 'none', 0, 0, 1, 0, 0, 'System administrators: audit log only, no case data (0043: metadata and audit scope)'
  UNION ALL SELECT 'Administrator / Executive Secretary', 'none', 0, 0, 0, 0, 0, 'BRS Admin personnel: no dashboard or reports'
  UNION ALL SELECT 'Evaluator (Pre-Case)', 'none', 0, 0, 0, 0, 0, 'Pre-case role: no reports'
  UNION ALL SELECT 'Pre-Case (Data Capturer)', 'none', 0, 0, 0, 0, 0, 'Pre-case role: no reports'
  UNION ALL SELECT 'External Directorate', 'none', 0, 0, 0, 0, 0, 'Not in the signed BRS: blocked until the City approves a scope'
  UNION ALL SELECT 'Train-the-Trainer', 'none', 0, 0, 0, 0, 0, 'No case data'
  UNION ALL SELECT 'general-user', 'none', 0, 0, 0, 0, 0, 'Base role held by everyone: grants nothing on its own'
) seed
WHERE NOT EXISTS (SELECT 1 FROM rpt_role_access);

CREATE TABLE IF NOT EXISTS rpt_role_grant (
  grant_key VARCHAR(64) NOT NULL PRIMARY KEY COMMENT 'Stable slot, not a display name',
  role_ids JSON NOT NULL COMMENT 'CMS roles.id values. A rename of roles.name does not change access.',
  scope ENUM('all','org','participant','none') NOT NULL DEFAULT 'none',
  can_dashboard TINYINT NOT NULL DEFAULT 0,
  can_cases TINYINT NOT NULL DEFAULT 0,
  can_audit TINYINT NOT NULL DEFAULT 0,
  can_export TINYINT NOT NULL DEFAULT 0,
  can_see_names TINYINT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rpt_role_grant (grant_key, role_ids, scope, can_dashboard, can_cases, can_audit, can_export, can_see_names, note)
SELECT
  CASE ra.role_name
    WHEN 'Executive Management' THEN 'executive_management'
    WHEN 'Operational Management' THEN 'operational_management'
    WHEN 'Investigator' THEN 'investigator'
    WHEN 'super-admin' THEN 'super_admin'
    WHEN 'Administrator / Executive Secretary' THEN 'administrator'
    WHEN 'Evaluator (Pre-Case)' THEN 'evaluator_pre_case'
    WHEN 'Pre-Case (Data Capturer)' THEN 'pre_case_capturer'
    WHEN 'External Directorate' THEN 'external_directorate'
    WHEN 'Train-the-Trainer' THEN 'train_the_trainer'
    WHEN 'general-user' THEN 'general_user'
    ELSE LEFT(CONCAT('custom_', LOWER(REPLACE(REPLACE(REPLACE(ra.role_name, ' ', '_'), '/', '_'), '-', '_'))), 64)
  END,
  COALESCE((SELECT JSON_ARRAYAGG(r.id) FROM `ciid_console_aims_net_za`.roles r WHERE r.name = ra.role_name), JSON_ARRAY()),
  ra.scope, ra.can_dashboard, ra.can_cases, ra.can_audit, ra.can_export, ra.can_see_names, ra.note
FROM rpt_role_access ra
WHERE NOT EXISTS (SELECT 1 FROM rpt_role_grant);

INSERT INTO rpt_role_grant (grant_key, role_ids, scope, can_dashboard, can_cases, can_audit, can_export, can_see_names, note)
SELECT seed.grant_key,
       COALESCE((SELECT JSON_ARRAYAGG(r.id) FROM `ciid_console_aims_net_za`.roles r WHERE r.name = seed.role_name), JSON_ARRAY()),
       seed.scope, seed.d, seed.c, seed.a, seed.e, seed.n, seed.note
FROM (
  SELECT 'executive_management' AS grant_key, 'Executive Management' AS role_name, 'all' AS scope, 1 AS d, 1 AS c, 1 AS a, 1 AS e, 1 AS n,
         'BRS Management (Director, Deputy Head): all cases, dashboard, audit log' AS note
  UNION ALL SELECT 'operational_management', 'Operational Management', 'org', 1, 1, 0, 1, 1,
         'BRS Management (Manager, Senior Manager): cases in the org units linked to them in AIMS'
  UNION ALL SELECT 'investigator', 'Investigator', 'participant', 0, 1, 0, 0, 1,
         'BRS 4.6.3: reports on allocated cases only; no dashboard; no downloads'
  UNION ALL SELECT 'super_admin', 'super-admin', 'none', 0, 0, 1, 0, 0,
         'System administrators: audit log only, no case data (0043: metadata and audit scope)'
  UNION ALL SELECT 'administrator', 'Administrator / Executive Secretary', 'none', 0, 0, 0, 0, 0,
         'BRS Admin personnel: no dashboard or reports'
  UNION ALL SELECT 'evaluator_pre_case', 'Evaluator (Pre-Case)', 'none', 0, 0, 0, 0, 0, 'Pre-case role: no reports'
  UNION ALL SELECT 'pre_case_capturer', 'Pre-Case (Data Capturer)', 'none', 0, 0, 0, 0, 0, 'Pre-case role: no reports'
  UNION ALL SELECT 'external_directorate', 'External Directorate', 'none', 0, 0, 0, 0, 0,
         'Not in the signed BRS: blocked until the City approves a scope'
  UNION ALL SELECT 'train_the_trainer', 'Train-the-Trainer', 'none', 0, 0, 0, 0, 0, 'No case data'
  UNION ALL SELECT 'general_user', 'general-user', 'none', 0, 0, 0, 0, 0, 'Base role held by everyone: grants nothing on its own'
) seed
WHERE NOT EXISTS (SELECT 1 FROM rpt_role_grant);
