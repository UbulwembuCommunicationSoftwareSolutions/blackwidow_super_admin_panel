<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

final class AimsFilters
{
    /** @var array<string, string> */
    public const EQ = [
        'classification' => 'c.classification',
        'directorate' => 'c.directorate',
        'department' => 'c.department',
        'allegation_type' => 'c.allegation_type',
        'source' => 'c.source',
        'division' => 'c.division',
        'priority' => 'c.priority',
        'status_bucket' => 'c.status_bucket',
        'status' => 'c.status',
        'fin_year' => 'c.fin_year',
        'received_month' => 'c.received_month',
        'root_cause' => 'c.root_cause',
        'intake_channel' => 'c.intake_channel',
    ];

    /** @var array<string, array{0: int, 1: int}> */
    public const AGE_BANDS = [
        '0-30 days' => [0, 30],
        '31-60 days' => [31, 60],
        '61-90 days' => [61, 90],
        'Over 90 days' => [91, 100000],
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $filters = [];

        foreach (array_keys(self::EQ) as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                $filters[$key] = mb_substr($input[$key], 0, 191);
            }
        }

        foreach (['from', 'to'] as $key) {
            if (! empty($input[$key]) && is_string($input[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$key])) {
                $filters[$key] = $input[$key];
            }
        }

        if (isset($input['fin_quarter']) && in_array((string) $input['fin_quarter'], ['1', '2', '3', '4'], true)) {
            $filters['fin_quarter'] = (int) $input['fin_quarter'];
        }

        if (isset($input['state']) && is_string($input['state']) && in_array($input['state'], ['open', 'closed', 'overdue'], true)) {
            $filters['state'] = $input['state'];
        }

        foreach (['category', 'outcome', 'age_band'] as $key) {
            if (! empty($input[$key]) && is_string($input[$key])) {
                $filters[$key] = mb_substr($input[$key], 0, 191);
            }
        }

        // Whether the case came through Pre-Case: 1 with intake data, 0 without.
        if (isset($input['has_intake']) && in_array((string) $input['has_intake'], ['0', '1'], true)) {
            $filters['has_intake'] = (int) $input['has_intake'];
        }

        if (! empty($input['owner']) && ctype_digit((string) $input['owner'])) {
            $filters['owner'] = (int) $input['owner'];
        }

        if (! empty($input['q']) && is_string($input['q'])) {
            $filters['q'] = mb_substr(trim($input['q']), 0, 40);
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: list<mixed>}
     */
    public static function clause(array $filters, string $aimsDatabase, string $reportingDatabase): array
    {
        $parts = [];
        $params = [];

        foreach (self::EQ as $key => $column) {
            if (isset($filters[$key])) {
                $parts[] = "$column = ?";
                $params[] = (string) $filters[$key];
            }
        }

        if (isset($filters['fin_quarter'])) {
            $parts[] = 'c.fin_quarter = ?';
            $params[] = $filters['fin_quarter'];
        }
        if (isset($filters['has_intake'])) {
            $parts[] = 'c.has_intake = ?';
            $params[] = $filters['has_intake'];
        }
        if (isset($filters['from'])) {
            $parts[] = 'c.received_date >= ?';
            $params[] = $filters['from'];
        }
        if (isset($filters['to'])) {
            $parts[] = 'c.received_date <= ?';
            $params[] = $filters['to'];
        }
        if (isset($filters['state'])) {
            $parts[] = ['open' => 'c.is_closed = 0', 'closed' => 'c.is_closed = 1', 'overdue' => 'c.is_overdue = 1'][$filters['state']];
        }
        if (isset($filters['category'])) {
            $parts[] = 'c.case_id IN (SELECT case_id FROM '.SqlName::qualify($reportingDatabase, 'v_case_category').' WHERE category = ?)';
            $params[] = $filters['category'];
        }
        if (isset($filters['outcome'])) {
            $parts[] = 'c.case_id IN (SELECT x.docket_id FROM '.SqlName::qualify($aimsDatabase, 'docket_docket_tag').' x JOIN '.SqlName::qualify($aimsDatabase, 'docket_tags')
                .' t ON t.id = x.docket_tag_id WHERE t.name = ? AND x.deleted_at IS NULL)';
            $params[] = $filters['outcome'];
        }
        if (isset($filters['owner'])) {
            $parts[] = 'c.case_id IN (SELECT case_id FROM '.SqlName::qualify($reportingDatabase, 'v_case_owner').' WHERE user_id = ? AND is_current = 1)';
            $params[] = $filters['owner'];
        }
        if (isset($filters['age_band']) && isset(self::AGE_BANDS[$filters['age_band']])) {
            [$low, $high] = self::AGE_BANDS[$filters['age_band']];
            $parts[] = 'c.is_closed = 0 AND c.days_open BETWEEN ? AND ?';
            $params[] = $low;
            $params[] = $high;
        }
        if (isset($filters['q'])) {
            $parts[] = 'c.reference LIKE ?';
            $params[] = '%'.addcslashes($filters['q'], '%_\\').'%';
        }

        return [$parts !== [] ? implode(' AND ', $parts) : '1=1', $params];
    }
}
