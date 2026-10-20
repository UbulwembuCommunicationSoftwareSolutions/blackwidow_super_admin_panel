/* =====================================================================================
   AIMS Reporting 1.0.0: post-install checks (ethekwini: `aims_rpt` reading `ciid_console_aims_net_za`).
   Run after scripts 02 and 03, in phpMyAdmin's SQL tab. One result grid comes back.
   In phpMyAdmin, click "+ Options", choose "Full texts" and Go, so long results are not cut off;
   then "Show all" and send a screenshot or an export of the grid.
   Read-only. It returns counts and configuration names (statuses, divisions, roles) only:
   no case content, and no names or contact details of people.
   Run it again after every AIMS release.
   ===================================================================================== */
SET SESSION group_concat_max_len = 16384;

SELECT n AS `#`, area, check_name, result, expected FROM (
  SELECT 1 AS n,
         CONVERT('Environment' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS area,
         CONVERT('MySQL version' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS check_name,
         CONVERT(VERSION() USING utf8mb4) COLLATE utf8mb4_unicode_ci AS result,
         CONVERT('8.0.x' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS expected
  UNION ALL SELECT 2, 'Environment', 'Reporting views installed / of which run with the installer''s rights',
         CONCAT((SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'aims_rpt'), ' / ',
                (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = 'aims_rpt' AND SECURITY_TYPE = 'DEFINER')),
         '16 / 0'
  UNION ALL SELECT 3, 'Environment', 'Calendar days / public holidays loaded',
         CONCAT((SELECT COUNT(*) FROM `aims_rpt`.rpt_calendar), ' / ', (SELECT COUNT(*) FROM `aims_rpt`.rpt_public_holiday)),
         '14976 / 558 or more'
  UNION ALL SELECT 4, 'Environment', 'Schema version / AIMS database the views read',
         CONCAT((SELECT v FROM `aims_rpt`.rpt_settings WHERE k = 'schema_version'), ' / ',
                (SELECT v FROM `aims_rpt`.rpt_settings WHERE k = 'aims_database')),
         '1.0.0 / the aims_db named in config.php'
  UNION ALL SELECT 5, 'Clock', 'Minutes from the latest AIMS audit entry to South African time now / offset setting',
         CONCAT(TIMESTAMPDIFF(MINUTE, (SELECT MAX(created_at) FROM `ciid_console_aims_net_za`.audits), UTC_TIMESTAMP() + INTERVAL 120 MINUTE),
                ' / ', (SELECT aims_offset_minutes FROM `aims_rpt`.rpt_clock WHERE id = 1)),
         'Near 0 (AIMS keeps SA time): offset 0. Near 120 (AIMS keeps UTC): set rpt_clock.aims_offset_minutes to 120'
  UNION ALL SELECT 6, 'Clock', 'MySQL time zone (global / session) and MySQL clock',
         CONCAT(@@global.time_zone, ' / ', @@session.time_zone, ' / ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i')),
         'Information only: the reports use South African time whatever this shows'

  UNION ALL SELECT 10, 'Cases', 'Live cases in AIMS (not test, not deleted)',
         (SELECT COUNT(*) FROM `ciid_console_aims_net_za`.dockets WHERE is_test = 0 AND deleted_at IS NULL), '-'
  UNION ALL SELECT 11, 'Cases', 'Cases the reports can see',
         (SELECT COUNT(*) FROM `aims_rpt`.v_case), 'Same as #10'
  UNION ALL SELECT 12, 'Cases', 'Cases with no org unit (division)',
         (SELECT COUNT(*) FROM `aims_rpt`.v_case WHERE division IS NULL), '0'
  UNION ALL SELECT 13, 'Mapping', 'AIMS statuses missing from the stage map (status: cases now)',
         (SELECT COALESCE(GROUP_CONCAT(CONCAT(s.name, ': ', (SELECT COUNT(*) FROM `ciid_console_aims_net_za`.dockets d
                    WHERE d.docket_status_id = s.id AND d.is_test = 0 AND d.deleted_at IS NULL)) ORDER BY s.name SEPARATOR '; '), 'none')
            FROM `ciid_console_aims_net_za`.docket_statuses s
           WHERE s.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM `aims_rpt`.rpt_status_map m WHERE m.status_name = s.name)),
         'none'
  UNION ALL SELECT 14, 'Mapping', 'AIMS divisions (org level 5) missing from the classification map',
         (SELECT COALESCE(GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR '; '), 'none')
            FROM `ciid_console_aims_net_za`.company_level_fives l
           WHERE l.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM `aims_rpt`.rpt_division_class dc WHERE dc.division_name = l.name)),
         'none (a missing division shows as OTHER)'
  UNION ALL SELECT 15, 'Cases', 'Closed cases / closure date from AIMS closed date / from the audit trail / from last update',
         (SELECT CONCAT(COUNT(*), ' / ', SUM(d.closed_at IS NOT NULL), ' / ',
                        SUM(d.closed_at IS NULL AND c.closed_at <> d.updated_at + INTERVAL ck.aims_offset_minutes MINUTE), ' / ',
                        SUM(d.closed_at IS NULL AND c.closed_at = d.updated_at + INTERVAL ck.aims_offset_minutes MINUTE))
            FROM `aims_rpt`.v_case c JOIN `ciid_console_aims_net_za`.dockets d ON d.id = c.case_id JOIN `aims_rpt`.rpt_clock ck ON ck.id = 1
           WHERE c.is_closed = 1),
         'Last number low: a later edit to such a case moves its closure date'
  UNION ALL SELECT 16, 'Cases', 'Closed cases with no working-hours figure / cases outside the 2000-2040 calendar',
         CONCAT((SELECT COUNT(*) FROM `aims_rpt`.v_case WHERE is_closed = 1 AND working_hours IS NULL), ' / ',
                (SELECT COUNT(*) FROM `aims_rpt`.v_case WHERE fin_year IS NULL)),
         '0 / 0'
  UNION ALL SELECT 17, 'Clock', 'Cases received per hour of day, SA time (hour: cases)',
         (SELECT COALESCE(GROUP_CONCAT(CONCAT(LPAD(h, 2, '0'), 'h: ', k) ORDER BY h SEPARATOR '  '), 'no cases')
            FROM (SELECT HOUR(received_at) AS h, COUNT(*) AS k FROM `aims_rpt`.v_case GROUP BY HOUR(received_at)) x),
         'Bulk between 07h and 16h. Bulk two hours earlier: see #5'
  UNION ALL SELECT 18, 'Cases', 'Open cases with no due date / closed with closure before receipt',
         CONCAT((SELECT COUNT(*) FROM `aims_rpt`.v_case WHERE is_closed = 0 AND due_date IS NULL), ' / ',
                (SELECT COUNT(*) FROM `aims_rpt`.v_case WHERE is_closed = 1 AND closed_at < received_at)),
         'Low / 0'

  UNION ALL SELECT 20, 'Access', 'AIMS roles: active users -> report scope',
         (SELECT GROUP_CONCAT(CONCAT(x.name, ': ', x.users, ' -> ', COALESCE(ra.scope, 'none'),
                 CASE WHEN ra.can_audit = 1 AND COALESCE(ra.scope, 'none') = 'none' THEN ' (audit log only)' ELSE '' END)
                 ORDER BY x.users DESC SEPARATOR '; ')
            FROM (SELECT r.id, r.name, COUNT(DISTINCT u.id) AS users
                    FROM `ciid_console_aims_net_za`.roles r
                    LEFT JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.role_id = r.id AND m.model_type LIKE '%User'
                    LEFT JOIN `ciid_console_aims_net_za`.users u ON u.id = m.model_id AND u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'
                   GROUP BY r.id, r.name) x
            LEFT JOIN (
              SELECT jt.role_id, GROUP_CONCAT(DISTINCT g.scope ORDER BY g.scope SEPARATOR '+') AS scope, MAX(g.can_audit) AS can_audit
                FROM `aims_rpt`.rpt_role_grant g
                JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt
               GROUP BY jt.role_id
            ) ra ON ra.role_id = x.id),
         'Matches the access rules CIID approved'
  UNION ALL SELECT 21, 'Access', 'Grants whose role ids are missing or empty',
         (SELECT COALESCE(GROUP_CONCAT(x.label ORDER BY x.label SEPARATOR '; '), 'none') FROM (
            SELECT CONCAT(g.grant_key, ' (no role id)') AS label
              FROM `aims_rpt`.rpt_role_grant g
             WHERE (g.scope <> 'none' OR g.can_audit = 1)
               AND JSON_LENGTH(g.role_ids) = 0
            UNION
            SELECT CONCAT(g.grant_key, ':', jt.role_id) AS label
              FROM `aims_rpt`.rpt_role_grant g
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt
             WHERE (g.scope <> 'none' OR g.can_audit = 1)
               AND NOT EXISTS (SELECT 1 FROM `ciid_console_aims_net_za`.roles r WHERE r.id = jt.role_id)
         ) x),
         'none'
  UNION ALL SELECT 22, 'Access', 'Active users who can open case reports / the audit log',
         CONCAT(
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.scope <> 'none'
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'), ' / ',
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.can_audit = 1
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active')),
         '-'
  UNION ALL SELECT 23, 'Access', 'Active users who see ALL cases / of whom also see complainant names',
         CONCAT(
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.scope = 'all'
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'), ' / ',
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.scope = 'all' AND g.can_see_names = 1
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active')),
         'Only the people CIID named (Director, Deputy Head)'
  UNION ALL SELECT 24, 'Access', 'Active users whose roles give different scopes (the widest wins)',
         (SELECT COUNT(*) FROM (
            SELECT u.id FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.scope <> 'none'
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'
             GROUP BY u.id HAVING COUNT(DISTINCT g.scope) > 1) x),
         'Low; each one confirmed with CIID'
  UNION ALL SELECT 25, 'Access', 'Operational managers with no org units linked (they would see nothing)',
         (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
            JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
            JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
            JOIN `aims_rpt`.rpt_role_grant g ON g.grant_key = 'operational_management'
            JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
           WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'
             AND NOT EXISTS (SELECT 1 FROM `ciid_console_aims_net_za`.user_company_level_five l WHERE l.user_id = u.id)),
         '0'
  UNION ALL SELECT 26, 'Access', 'Per operational manager: divisions (min-max) / org units (min-max) / cases visible (min-max)',
         (SELECT CONCAT(COALESCE(MIN(divs), 0), '-', COALESCE(MAX(divs), 0), ' / ', COALESCE(MIN(leaves), 0), '-', COALESCE(MAX(leaves), 0),
                        ' / ', COALESCE(MIN(cases), 0), '-', COALESCE(MAX(cases), 0))
            FROM (SELECT u.id, COUNT(DISTINCT o.division) AS divs, COUNT(DISTINCT l.company_level_five_id) AS leaves,
                         (SELECT COUNT(*) FROM `aims_rpt`.v_case_key c WHERE c.leaf_id IN
                            (SELECT l2.company_level_five_id FROM `ciid_console_aims_net_za`.user_company_level_five l2 WHERE l2.user_id = u.id)) AS cases
                    FROM `ciid_console_aims_net_za`.users u
                    JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
                    JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
                    JOIN `aims_rpt`.rpt_role_grant g ON g.grant_key = 'operational_management'
                    JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
                    JOIN `ciid_console_aims_net_za`.user_company_level_five l ON l.user_id = u.id
                    JOIN `aims_rpt`.v_org_leaf o ON o.leaf_id = l.company_level_five_id
                   WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'
                   GROUP BY u.id) x),
         'Divisions 1-1 when each manager runs one division (a division is 150 org units). 6 = sees every division'
  UNION ALL SELECT 27, 'Access', 'Investigators / of whom allocated no cases',
         CONCAT(
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.grant_key = 'investigator'
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'),
           ' / ',
           (SELECT COUNT(DISTINCT u.id) FROM `ciid_console_aims_net_za`.users u
              JOIN `ciid_console_aims_net_za`.model_has_roles m ON m.model_id = u.id AND m.model_type LIKE '%User'
              JOIN `ciid_console_aims_net_za`.roles r ON r.id = m.role_id
              JOIN `aims_rpt`.rpt_role_grant g ON g.grant_key = 'investigator'
              JOIN JSON_TABLE(g.role_ids, '$[*]' COLUMNS (role_id BIGINT UNSIGNED PATH '$')) jt ON jt.role_id = r.id
             WHERE u.active = 1 AND u.deleted_at IS NULL AND u.status = 'Active'
               AND NOT EXISTS (SELECT 1 FROM `ciid_console_aims_net_za`.docket_participants p
                                WHERE p.user_id = CAST(u.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                                  AND p.type = 'owner' AND p.rejected_at IS NULL AND p.deleted_at IS NULL)
               AND NOT EXISTS (SELECT 1 FROM `ciid_console_aims_net_za`.task_participants tp
                                JOIN `ciid_console_aims_net_za`.docket_task dt ON dt.task_id = tp.task_id AND dt.deleted_at IS NULL
                                JOIN `ciid_console_aims_net_za`.tasks t ON t.id = tp.task_id AND t.deleted_at IS NULL
                                WHERE tp.user_id = CAST(u.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
                                  AND tp.type IN ('leader', 'member') AND tp.rejected_at IS NULL AND tp.deleted_at IS NULL))),
         'Second number low (new staff only)'
) checks
ORDER BY n;
