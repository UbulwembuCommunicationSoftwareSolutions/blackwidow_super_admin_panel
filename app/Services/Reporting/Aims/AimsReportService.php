<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use App\Models\CustomerUser;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Auth\User as AuthUser;
use Throwable;

/**
 * Read-only AIMS case report. Query shape matches the standalone reporting app.
 */
final class AimsReportService
{
    public const VERSION = '1.0.0';

    /** @var array<string, string> */
    private const CASE_SORT = [
        'reference' => 'c.reference',
        'received' => 'c.received_at',
        'classification' => 'c.classification',
        'division' => 'c.division',
        'directorate' => 'c.directorate',
        'category' => 'c.primary_category',
        'status' => 'c.bucket_order, c.status',
        'priority' => "FIELD(c.priority, 'High', 'Medium', 'Low')",
        'days' => 'c.days_open',
        'due' => 'c.due_date',
    ];

    private const CASE_COLS = 'c.case_id, c.reference, c.received_date, c.classification, c.division, c.directorate, c.department,
    c.allegation_type, c.source, c.primary_category, c.status, c.status_bucket, c.priority, c.days_open,
    c.is_closed, c.is_overdue, c.due_date, DATE(c.closed_at) AS closed_date, c.classification_source, c.has_intake';

    /** @var array<string, string> */
    private const AUDIT_SOURCES = [
        'AIMS change' => 'v_audit_aims_change',
        'AIMS login' => 'v_audit_aims_login',
        'Report access' => 'v_audit_report_access',
    ];

    public function __construct(private readonly AimsReportConnection $db) {}

    /**
     * The current time in the report's time zone (South African time), whatever this API's own time zone is.
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone((string) config('reporting.timezone', 'Africa/Johannesburg')));
    }

    /**
     * @return array<string, mixed>
     */
    public function actorFor(AuthUser $user): array
    {
        if ($user instanceof User) {
            return self::staffActor($user, $this->aimsActor((string) $user->email));
        }

        if (! $user instanceof CustomerUser) {
            return $this->emptyActor('Unknown user', '');
        }

        $email = mb_strtolower(trim((string) $user->email_address));
        $actor = $this->aimsActor($email);

        if ($actor === null) {
            $actor = $this->emptyActor(trim($user->first_name.' '.$user->last_name), $email);
            $actor['missing_aims_user'] = true;
        }

        return $actor;
    }

    /**
     * Staff get the access of the active AIMS user with their email on this site, like anyone else.
     * Without one, or when that user's roles grant nothing, they get the audit log only and no case data,
     * the same as the AIMS super-admin grant.
     *
     * @param  array<string, mixed>|null  $aimsActor
     * @return array<string, mixed>
     */
    public static function staffActor(User $user, ?array $aimsActor): array
    {
        if ($aimsActor !== null && in_array(true, $aimsActor['can'], true)) {
            return ['staff_id' => $user->id] + $aimsActor;
        }

        return [
            'id' => null,
            'staff_id' => $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'roles' => ['staff'],
            'scopes' => [],
            'can' => [
                'dashboard' => false,
                'cases' => false,
                'audit' => true,
                'export' => false,
                'names' => false,
            ],
        ];
    }

    /**
     * The active AIMS user with this email and the access their CMS roles grant, or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    private function aimsActor(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $row = $this->db->one(
            'SELECT id, email, first_name, last_name, full_name, active, status, deleted_at FROM '.$this->db->aims('users').' WHERE LOWER(email) = ? LIMIT 1',
            [$email]
        );

        if (! $row || ! $this->userIsActive($row)) {
            return null;
        }

        $id = (int) $row['id'];
        $roleRows = $this->db->rows(
            'SELECT r.id, r.name FROM '.$this->db->aims('model_has_roles').' m JOIN '.$this->db->aims('roles').' r ON r.id = m.role_id'
            ." WHERE m.model_id = ? AND m.model_type LIKE '%User' ORDER BY r.name",
            [$id]
        );
        $roles = array_column($roleRows, 'name');
        $roleIds = array_map(static fn (array $role): int => (int) $role['id'], $roleRows);
        $grants = $roleIds === []
            ? []
            : $this->db->rows('SELECT * FROM '.$this->db->reporting('rpt_role_grant'));
        $access = self::capabilitiesFor($roleIds, $grants);
        $can = $access['can'];
        $scopeList = $access['scopes'];

        $name = trim((string) ($row['full_name'] ?: ($row['first_name'].' '.$row['last_name'])));

        return [
            'id' => $id,
            'name' => $name,
            'email' => $row['email'],
            'roles' => $roles,
            'scopes' => $scopeList,
            'can' => $can,
        ];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public function profile(array $actor, string $appName): array
    {
        return [
            'name' => $actor['name'],
            'email' => $actor['email'],
            'roles' => $actor['roles'],
            'can' => $actor['can'],
            'scope' => AimsScope::label($actor),
            'demo' => false,
            'app' => $appName,
            'version' => self::VERSION,
            'missing_aims_user' => (bool) ($actor['missing_aims_user'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $actor, array $filters, string $grain): array
    {
        $source = $this->dashSource($actor, $filters);
        [$table, $condition, $params] = $source;
        $from = "FROM $table c WHERE $condition";
        $kpi = $this->db->one(
            "SELECT COUNT(*) AS received, COALESCE(SUM(c.is_closed = 0), 0) AS open_n, COALESCE(SUM(c.is_closed = 1), 0) AS closed_n,
                     COALESCE(SUM(c.is_overdue), 0) AS overdue, COALESCE(SUM(c.is_closed = 0 AND c.priority = 'High'), 0) AS open_high,
                     ROUND(AVG(CASE WHEN c.is_closed = 0 THEN c.days_open END)) AS avg_open_days,
                     COALESCE(SUM(c.is_closed = 1 AND c.due_by IS NOT NULL AND c.closed_at <= c.due_by), 0) AS closed_on_time,
                     COALESCE(SUM(c.is_closed = 1 AND c.due_by IS NOT NULL), 0) AS closed_with_target,
                     COALESCE(SUM(c.has_intake = 1), 0) AS with_intake
              $from",
            $params
        ) ?? [];

        $period = ['year' => 'c.fin_year', 'quarter' => "CONCAT(c.fin_year, ' Q', c.fin_quarter)", 'month' => 'c.received_month'][$grain];
        $trend = $this->db->rows(
            "SELECT $period AS label, SUM(c.is_closed = 1) AS closed_n, SUM(c.is_closed = 0) AS open_n, COUNT(*) AS n
                   $from GROUP BY label ORDER BY label",
            $params
        );

        $closedPeriod = [
            'year' => 'k.fin_year',
            'quarter' => "CONCAT(k.fin_year, ' Q', k.fin_quarter)",
            'month' => "DATE_FORMAT(c.closed_at, '%Y-%m')",
        ][$grain];
        $turnaroundRows = $this->db->rows(
            "SELECT $closedPeriod AS period, c.primary_category AS category, c.working_hours AS wh
                FROM $table c LEFT JOIN ".$this->db->reporting('rpt_calendar')." k ON k.d = DATE(c.closed_at)
                WHERE $condition AND c.is_closed = 1 AND c.working_hours IS NOT NULL",
            $params
        );

        $byPeriod = [];
        $byCategory = [];
        $sum = 0.0;
        foreach ($turnaroundRows as $row) {
            $hours = (float) $row['wh'];
            $sum += $hours;
            $byPeriod[$row['period'] ?? '(outside calendar)'][] = $hours;
            $byCategory[$row['category'] ?? 'No category'][] = $hours;
        }
        $average = fn (array $values) => (int) round(array_sum($values) / max(1, count($values)));
        ksort($byPeriod);
        $turnaroundPeriod = [];
        foreach ($byPeriod as $label => $values) {
            $turnaroundPeriod[] = ['label' => $label, 'avg' => $average($values), 'n' => count($values)];
        }
        $turnaroundCategory = [];
        foreach ($byCategory as $label => $values) {
            $turnaroundCategory[] = ['label' => $label, 'avg' => $average($values), 'n' => count($values)];
        }
        usort($turnaroundCategory, fn ($left, $right) => $right['avg'] <=> $left['avg']);
        $kpi['avg_turnaround_hours'] = $turnaroundRows !== [] ? (int) round($sum / count($turnaroundRows)) : null;

        $ageCase = 'CASE '.implode(' ', array_map(
            fn ($band, $range) => "WHEN c.days_open BETWEEN {$range[0]} AND {$range[1]} THEN '$band'",
            array_keys(AimsFilters::AGE_BANDS),
            AimsFilters::AGE_BANDS
        )).' END';
        $age = $this->db->rows("SELECT c.classification AS cls, $ageCase AS band, COUNT(*) AS n $from AND c.is_closed = 0 GROUP BY cls, band", $params);

        $drillOrg = isset($filters['directorate']) ? 'c.department' : 'c.directorate';
        $statusExpr = isset($filters['status_bucket']) ? 'c.status' : 'c.status_bucket';

        $categories = $this->db->rows(
            'SELECT cc.category AS label, COUNT(*) AS n FROM '.$this->db->reporting('v_case_category')." cc JOIN $table c ON c.case_id = cc.case_id
                  WHERE $condition GROUP BY cc.category ORDER BY n DESC, label",
            $params
        );
        $workload = $this->db->rows(
            'SELECT o.user_id AS id, o.user_name AS label, SUM(c.is_closed = 0) AS open_n, SUM(c.is_closed = 1) AS closed_n,
                         ROUND(AVG(CASE WHEN c.is_closed = 0 THEN c.days_open END)) AS avg_open_days
                  FROM '.$this->db->reporting('v_case_owner')." o JOIN $table c ON c.case_id = o.case_id
                  WHERE o.is_current = 1 AND $condition GROUP BY o.user_id, o.user_name
                  ORDER BY open_n DESC, closed_n DESC LIMIT 15",
            $params
        );
        $outcomes = $this->db->rows(
            'SELECT t.name AS label, COUNT(*) AS n FROM '.$this->db->aims('docket_docket_tag').' x JOIN '.$this->db->aims('docket_tags')
            ." t ON t.id = x.docket_tag_id JOIN $table c ON c.case_id = x.docket_id WHERE x.deleted_at IS NULL AND $condition GROUP BY t.name ORDER BY n DESC",
            $params
        );

        return [
            'kpi' => $kpi,
            'grain' => $grain,
            'trend' => $trend,
            'turnaround_period' => $turnaroundPeriod,
            'turnaround_category' => $turnaroundCategory,
            'age' => $age,
            'age_bands' => array_keys(AimsFilters::AGE_BANDS),
            'status' => $this->grouped($source, $statusExpr, '', isset($filters['status_bucket']) ? 'n DESC, label' : 'MIN(c.bucket_order), label'),
            'status_level' => isset($filters['status_bucket']) ? 'status' : 'status_bucket',
            'classification' => $this->grouped($source, 'c.classification'),
            'division' => $this->grouped($source, 'c.division'),
            'org' => $this->grouped($source, $drillOrg),
            'org_level' => isset($filters['directorate']) ? 'department' : 'directorate',
            'allegation_type' => $this->grouped($source, 'c.allegation_type'),
            'source' => $this->grouped($source, 'c.source'),
            'priority' => $this->grouped($source, 'c.priority', '', "FIELD(label, 'High', 'Medium', 'Low')"),
            'category' => $categories,
            'workload' => $workload,
            'outcomes' => $outcomes,
            'root_cause' => $this->grouped($source, 'c.root_cause', 'AND c.is_closed = 1 AND c.root_cause IS NOT NULL'),
        ] + $this->preCaseBreakdown($source);
    }

    /**
     * Dashboard datasets from the Pre-Case intake data. Most cases have none until the Pre-Case
     * hand-over is live, so each dataset counts only the cases that do, and the share shows how many.
     *
     * @param  array{0: string, 1: string, 2: list<mixed>}  $source
     * @return array{intake_channel: list<array<string, mixed>>, value_involved: list<array<string, mixed>>, intake_share: list<array<string, mixed>>, classification_source: list<array<string, mixed>>}
     */
    public function preCaseBreakdown(array $source): array
    {
        [$table, $condition, $params] = $source;

        return [
            'intake_channel' => $this->grouped($source, "COALESCE(c.intake_channel, 'Not recorded')", 'AND c.has_intake = 1'),
            'value_involved' => $this->db->rows(
                "SELECT c.classification AS label, COUNT(*) AS n, SUM(c.estimated_financial_impact) AS total
                   FROM $table c WHERE $condition AND c.estimated_financial_impact IS NOT NULL
                   GROUP BY c.classification ORDER BY total DESC, label",
                $params
            ),
            'intake_share' => $this->db->rows(
                "SELECT CASE WHEN c.has_intake = 1 THEN 'From Pre-Case' ELSE 'Captured in AIMS' END AS label, c.has_intake, COUNT(*) AS n
                   FROM $table c WHERE $condition GROUP BY c.has_intake ORDER BY c.has_intake DESC",
                $params
            ),
            'classification_source' => $this->grouped($source, 'c.classification_source'),
        ];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function caseList(array $actor, array $filters, int $page, int $per, string $sort, string $direction): array
    {
        [$from, $params] = $this->base($actor, $filters);
        $order = (self::CASE_SORT[$sort] ?? self::CASE_SORT['received']).($direction === 'asc' ? ' ASC' : ' DESC');
        $total = (int) ($this->db->one("SELECT COUNT(*) AS n $from", $params)['n'] ?? 0);
        $per = in_array($per, [25, 50, 100], true) ? $per : 25;
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $per;
        $list = $this->db->rows('SELECT '.self::CASE_COLS." $from ORDER BY $order, c.case_id DESC LIMIT $per OFFSET $offset", $params);

        if ($list !== []) {
            $ids = array_map('intval', array_column($list, 'case_id'));
            $owners = $this->db->rows(
                'SELECT c.case_id, '.$this->extraColumns()['owners'].' AS owners FROM '.$this->db->reporting('v_case').' c WHERE c.case_id IN ('
                .implode(',', array_fill(0, count($ids), '?')).')',
                $ids
            );
            $map = array_column($owners, 'owners', 'case_id');
            foreach ($list as &$row) {
                $row['owners'] = $map[$row['case_id']] ?? null;
            }
            unset($row);
        }

        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'rows' => $list];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function caseExport(array $actor, array $filters): array
    {
        $limit = max(1, (int) config('reporting.export_row_limit', 5000));
        [$scope, $scopeParams] = AimsScope::clause($actor, $this->db->aimsDatabase());
        [$where, $whereParams] = AimsFilters::clause($filters, $this->db->aimsDatabase(), $this->db->reportingDatabase());

        return $this->db->rows(
            'SELECT c.reference, c.ack_number, c.received_date, c.fin_year, c.classification,
        CASE WHEN c.classification_source = \'pre_case\' THEN \'Pre-Case\' ELSE \'Division mapping (provisional)\' END AS classification_from,
        c.intake_channel, c.estimated_financial_impact, c.division, c.directorate, c.department,
        c.allegation_type, c.source, l.categories, l.sub_categories, c.status, c.status_bucket, c.priority, l.owners, l.complainants,
        c.due_date, DATE(c.closed_at) AS closed_date, c.days_open, c.working_hours, c.is_overdue, c.root_cause, l.outcome_tags
        FROM '.$this->db->reporting('v_case').' c LEFT JOIN '.$this->db->reporting('v_case_lists')." l ON l.case_id = c.case_id
        WHERE $scope AND $where ORDER BY c.received_at DESC, c.case_id DESC LIMIT $limit",
            array_merge($scopeParams, $whereParams)
        );
    }

    /**
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>|null
     */
    public function caseDetail(array $actor, int $id): ?array
    {
        [$scope, $scopeParams] = AimsScope::clause($actor, $this->db->aimsDatabase());
        $extra = $this->extraColumns();
        $case = $this->db->one(
            "SELECT c.*, DATE(c.closed_at) AS closed_date, {$extra['owners']} AS owners, {$extra['categories']} AS categories,
                     {$extra['sub_categories']} AS sub_categories, {$extra['complainants']} AS complainants, {$extra['outcome_tags']} AS outcome_tags
              FROM ".$this->db->reporting('v_case')." c WHERE c.case_id = ? AND $scope",
            array_merge([$id], $scopeParams)
        );

        if (! $case) {
            return null;
        }

        $case['owner_history'] = $this->db->rows(
            'SELECT user_name, accepted, declined, allocated_at, removed_at, is_current FROM '.$this->db->reporting('v_case_owner')
            .' WHERE case_id = ? ORDER BY allocated_at',
            [$id]
        );
        $case['history'] = $this->db->rows(
            'SELECT at, kind, description, actor FROM '.$this->db->reporting('v_case_history').' WHERE case_id = ? ORDER BY at, kind',
            [$id]
        );
        $case['tasks'] = $this->db->rows(
            'SELECT t.reference, t.title, s.description AS status, t.due_date + INTERVAL ck.aims_offset_minutes MINUTE AS due_date
          FROM '.$this->db->aims('docket_task').' dt JOIN '.$this->db->aims('tasks').' t ON t.id = dt.task_id JOIN '.$this->db->reporting('rpt_clock').' ck ON ck.id = 1
          LEFT JOIN '.$this->db->aims('task_statuses').' s ON s.id = t.task_status_id
          WHERE dt.docket_id = ? AND dt.deleted_at IS NULL AND t.deleted_at IS NULL ORDER BY t.created_at',
            [$id]
        );
        $case['complainant_names'] = ($actor['can']['names'] ?? false)
            ? array_column($this->db->rows(
                "SELECT TRIM(CONCAT(first_name, ' ', last_name)) AS n FROM ".$this->db->aims('docket_reporters')
                .' WHERE docket_id = ? AND deleted_at IS NULL ORDER BY id',
                [$id]
            ), 'n')
            : null;
        unset($case['leaf_id']);

        return $case;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function auditList(array $input, int $page): array
    {
        $isDate = fn ($key) => ! empty($input[$key]) && is_string($input[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$key]);
        $from = $isDate('from') ? $input['from'] : self::now()->modify('-30 days')->format('Y-m-d');
        $to = $isDate('to') ? $input['to'] : null;
        $parts = ['l.at >= ?'];
        $params = [$from.' 00:00:00'];
        if ($to) {
            $parts[] = 'l.at < ?';
            $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00';
        }
        if (! empty($input['actor']) && is_string($input['actor'])) {
            $parts[] = 'l.actor LIKE ?';
            $params[] = '%'.addcslashes(mb_substr($input['actor'], 0, 60), '%_\\').'%';
        }
        $where = 'WHERE '.implode(' AND ', $parts);
        $sources = self::AUDIT_SOURCES;
        if (! empty($input['source']) && is_string($input['source']) && isset($sources[$input['source']])) {
            $sources = [$input['source'] => $sources[$input['source']]];
        }
        $per = 50;
        $total = 0;
        foreach ($sources as $view) {
            $total += (int) ($this->db->one('SELECT COUNT(*) AS n FROM '.$this->db->reporting($view)." l $where", $params)['n'] ?? 0);
        }
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, $page), $pages);
        $need = $page * $per;
        $all = [];
        foreach ($sources as $view) {
            $all = array_merge($all, $this->db->rows(
                'SELECT l.row_id, l.at, l.source, l.actor, l.event, l.object_type, l.object_id, l.detail, l.ip FROM '
                .$this->db->reporting($view)." l $where ORDER BY l.at DESC, l.row_id DESC LIMIT $need",
                $params
            ));
        }
        usort($all, fn ($left, $right) => [$right['at'], $right['row_id']] <=> [$left['at'], $left['row_id']]);
        $list = array_slice($all, ($page - 1) * $per, $per);
        foreach ($list as &$row) {
            unset($row['row_id']);
        }
        unset($row);

        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'rows' => $list, 'from' => $from, 'to' => $to];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public function filterOptions(array $actor): array
    {
        [$scope, $scopeParams] = AimsScope::clause($actor, $this->db->aimsDatabase());
        $allowed = null;
        if ($scope !== '1=1') {
            $allowed = array_flip(array_map('intval', array_column(
                $this->db->rows('SELECT DISTINCT c.leaf_id FROM '.$this->db->reporting('v_case_key')." c WHERE $scope", $scopeParams),
                'leaf_id'
            )));
        }

        $sets = [
            'classification' => [],
            'directorate' => [],
            'department' => [],
            'allegation_type' => [],
            'source' => [],
            'division' => [],
        ];
        foreach ($this->db->rows('SELECT leaf_id, classification, directorate, department, allegation_type, source, division FROM '.$this->db->reporting('v_org_leaf')) as $leaf) {
            if ($allowed !== null && ! isset($allowed[(int) $leaf['leaf_id']])) {
                continue;
            }
            foreach ($sets as $key => $_) {
                if ($leaf[$key] !== null && $leaf[$key] !== '') {
                    $sets[$key][$leaf[$key]] = true;
                }
            }
        }

        $list = function (array $set): array {
            $values = array_keys($set);
            usort($values, 'strnatcasecmp');

            return array_map('strval', $values);
        };
        $order = ['INV' => 1, 'OMB' => 2, 'INF' => 3, 'OTHER' => 4];
        $classifications = array_map('strval', array_keys($sets['classification']));
        usort($classifications, fn ($left, $right) => ($order[$left] ?? 9) <=> ($order[$right] ?? 9));
        $column = fn (string $sql, array $params = []) => array_column($this->db->rows($sql, $params), 'v');

        return [
            'classification' => $classifications,
            'directorate' => $list($sets['directorate']),
            'department' => $list($sets['department']),
            'allegation_type' => $list($sets['allegation_type']),
            'source' => $list($sets['source']),
            'division' => $list($sets['division']),
            'intake_channel' => $column(
                'SELECT DISTINCT di.intake_channel AS v FROM '.$this->db->aims('docket_intake_details').' di
              WHERE di.intake_channel IS NOT NULL AND di.intake_channel <> \'\' ORDER BY v'
            ),
            'category' => $column('SELECT name AS v FROM '.$this->db->aims('docket_categories').' WHERE deleted_at IS NULL ORDER BY name'),
            'priority' => $column('SELECT name AS v FROM '.$this->db->aims('docket_priorities')." WHERE deleted_at IS NULL ORDER BY FIELD(v, 'High', 'Medium', 'Low'), v"),
            'status_bucket' => $column('SELECT bucket AS v FROM '.$this->db->reporting('rpt_status_map').' GROUP BY bucket ORDER BY MIN(bucket_order)'),
            'status' => $column('SELECT name AS v FROM '.$this->db->aims('docket_statuses').' WHERE deleted_at IS NULL ORDER BY name'),
            'fin_year' => $column(
                'SELECT DISTINCT k.fin_year AS v FROM '.$this->db->aims('dockets').' d JOIN '.$this->db->reporting('rpt_clock').' ck ON ck.id = 1
              LEFT JOIN '.$this->db->aims('docket_intake_details').' di ON di.docket_id = d.id JOIN '
                .$this->db->reporting('rpt_calendar').' k ON k.d = DATE(COALESCE(di.date_received, d.created_at) + INTERVAL ck.aims_offset_minutes MINUTE)
              WHERE d.is_test = 0 AND d.deleted_at IS NULL ORDER BY v DESC'
            ),
            'provisional' => [
                'classification' => (int) ($this->db->one('SELECT COUNT(*) AS n FROM '.$this->db->reporting('rpt_division_class').' WHERE provisional = 1')['n'] ?? 0) > 0,
                'status_bucket' => (int) ($this->db->one('SELECT COUNT(*) AS n FROM '.$this->db->reporting('rpt_status_map').' WHERE provisional = 1')['n'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $actor
     */
    public function logAccess(array $actor, string $action, string $detail, ?string $ip): void
    {
        try {
            $at = self::now()->format('Y-m-d H:i:s');
            $staffId = $actor['staff_id'] ?? null;
            if ($staffId !== null) {
                $detail = 'staff_user_id='.$staffId.($detail !== '' ? ' '.$detail : '');
            }
            $statement = $this->db->pdo()->prepare(
                'INSERT INTO '.$this->db->reporting('rpt_access_log').' (at, user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $at,
                $actor['id'] ?? null,
                isset($actor['name']) ? mb_substr((string) $actor['name'], 0, 191) : null,
                mb_substr($action, 0, 40),
                mb_substr($detail, 0, 500),
                $ip !== null ? mb_substr($ip, 0, 45) : null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function base(array $actor, array $filters): array
    {
        [$scope, $scopeParams] = AimsScope::clause($actor, $this->db->aimsDatabase());
        [$where, $whereParams] = AimsFilters::clause($filters, $this->db->aimsDatabase(), $this->db->reportingDatabase());

        return ['FROM '.$this->db->reporting('v_case')." c WHERE $scope AND $where", array_merge($scopeParams, $whereParams)];
    }

    /**
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string, 2: list<mixed>}
     */
    private function dashSource(array $actor, array $filters): array
    {
        [$scope, $scopeParams] = AimsScope::clause($actor, $this->db->aimsDatabase());
        [$where, $whereParams] = AimsFilters::clause($filters, $this->db->aimsDatabase(), $this->db->reportingDatabase());
        $temporary = $this->db->reporting('tmp_dash');

        try {
            $this->db->exec("DROP TEMPORARY TABLE IF EXISTS $temporary");
            $statement = $this->db->pdo()->prepare(
                "CREATE TEMPORARY TABLE $temporary (PRIMARY KEY (case_id)) SELECT c.* FROM ".$this->db->reporting('v_case')." c WHERE $scope AND $where"
            );
            $statement->execute(array_merge($scopeParams, $whereParams));

            return [$temporary, '1=1', []];
        } catch (Throwable $e) {
            report($e);

            return [$this->db->reporting('v_case'), "$scope AND $where", array_merge($scopeParams, $whereParams)];
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: list<mixed>}  $source
     * @return list<array<string, mixed>>
     */
    private function grouped(array $source, string $expression, string $extra = '', string $order = 'n DESC, label'): array
    {
        [$table, $condition, $params] = $source;

        return $this->db->rows("SELECT $expression AS label, COUNT(*) AS n FROM $table c WHERE $condition $extra GROUP BY label ORDER BY $order", $params);
    }

    /**
     * @return array<string, string>
     */
    private function extraColumns(): array
    {
        return [
            'owners' => "(SELECT GROUP_CONCAT(COALESCE(u.full_name, CONCAT_WS(' ', u.first_name, u.last_name)) ORDER BY pt.id SEPARATOR '; ') FROM "
                .$this->db->aims('docket_participants').' pt JOIN '.$this->db->aims('users')." u ON u.id = CAST(pt.user_id AS UNSIGNED)
              WHERE pt.docket_id = c.case_id AND pt.type = 'owner' AND pt.deleted_at IS NULL AND pt.rejected_at IS NULL)",
            'categories' => "(SELECT GROUP_CONCAT(cat.name ORDER BY x.id SEPARATOR '; ') FROM ".$this->db->aims('docket_docket_category').' x JOIN '
                .$this->db->aims('docket_categories').' cat ON cat.id = x.docket_category_id WHERE x.docket_id = c.case_id AND x.deleted_at IS NULL)',
            'sub_categories' => "(SELECT GROUP_CONCAT(sc.description ORDER BY x.id SEPARATOR '; ') FROM ".$this->db->aims('docket_docket_sub_category').' x JOIN '
                .$this->db->aims('docket_sub_categories').' sc ON sc.id = x.docket_sub_category_id WHERE x.docket_id = c.case_id)',
            'complainants' => '(SELECT COUNT(*) FROM '.$this->db->aims('docket_reporters').' r WHERE r.docket_id = c.case_id AND r.deleted_at IS NULL)',
            'outcome_tags' => "(SELECT GROUP_CONCAT(tg.name ORDER BY x.id SEPARATOR '; ') FROM ".$this->db->aims('docket_docket_tag').' x JOIN '
                .$this->db->aims('docket_tags').' tg ON tg.id = x.docket_tag_id WHERE x.docket_id = c.case_id AND x.deleted_at IS NULL)',
        ];
    }

    /**
     * A grant applies when one of the user's CMS role ids is in its role_ids array.
     * The display name is not consulted. Several grants: the widest scope wins.
     * An audit-only grant stays available when the user has no case scope.
     *
     * @param  list<int>  $roleIds
     * @param  list<array<string, mixed>>  $grants
     * @return array{scopes: list<string>, can: array<string, bool>}
     */
    public static function capabilitiesFor(array $roleIds, array $grants): array
    {
        $roleIds = array_map(static fn (mixed $id): int => (int) $id, $roleIds);
        $can = [
            'dashboard' => false,
            'cases' => false,
            'audit' => false,
            'export' => false,
            'names' => false,
        ];
        $scopes = [];

        foreach ($grants as $grant) {
            if (array_intersect($roleIds, self::grantRoleIds($grant['role_ids'] ?? [])) === []) {
                continue;
            }
            if ($grant['scope'] !== 'none') {
                $scopes[$grant['scope']] = true;
            }
            $can['dashboard'] = $can['dashboard'] || (int) $grant['can_dashboard'] === 1;
            $can['cases'] = $can['cases'] || (int) $grant['can_cases'] === 1;
            $can['audit'] = $can['audit'] || (int) $grant['can_audit'] === 1;
            $can['export'] = $can['export'] || (int) $grant['can_export'] === 1;
            $can['names'] = $can['names'] || (int) $grant['can_see_names'] === 1;
        }

        $scopeList = array_keys($scopes);
        if (in_array('all', $scopeList, true)) {
            $scopeList = ['all'];
        }
        if ($scopeList === []) {
            $can['dashboard'] = $can['cases'] = $can['export'] = $can['names'] = false;
        }

        return ['scopes' => $scopeList, 'can' => $can];
    }

    /**
     * @return list<int>
     */
    private static function grantRoleIds(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        if (! is_array($stored)) {
            return [];
        }

        $ids = [];
        foreach ($stored as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function userIsActive(array $user): bool
    {
        return (int) $user['active'] === 1 && $user['deleted_at'] === null && strcasecmp((string) $user['status'], 'Active') === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyActor(string $name, string $email): array
    {
        return [
            'id' => null,
            'name' => $name,
            'email' => $email,
            'roles' => [],
            'scopes' => [],
            'can' => [
                'dashboard' => false,
                'cases' => false,
                'audit' => false,
                'export' => false,
                'names' => false,
            ],
        ];
    }
}
