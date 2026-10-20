<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

/**
 * Row scope from AIMS roles, matching the standalone reporting app.
 */
final class AimsScope
{
    /**
     * @param  array{id?: int|null, scopes?: list<string>}  $actor
     * @return array{0: string, 1: list<mixed>}
     */
    public static function clause(array $actor, string $aimsDatabase): array
    {
        $scopes = $actor['scopes'] ?? [];

        if (in_array('all', $scopes, true)) {
            return ['1=1', []];
        }

        $parts = [];
        $params = [];

        if (in_array('org', $scopes, true)) {
            $parts[] = 'c.leaf_id IN (SELECT company_level_five_id FROM '.SqlName::qualify($aimsDatabase, 'user_company_level_five').' WHERE user_id = ?)';
            $params[] = $actor['id'];
        }

        if (in_array('participant', $scopes, true)) {
            $parts[] = '(c.case_id IN (SELECT docket_id FROM '.SqlName::qualify($aimsDatabase, 'docket_participants')
                ." WHERE user_id = ? AND type = 'owner' AND rejected_at IS NULL AND deleted_at IS NULL)"
                .' OR c.case_id IN (SELECT dt.docket_id FROM '.SqlName::qualify($aimsDatabase, 'docket_task').' dt'
                .' JOIN '.SqlName::qualify($aimsDatabase, 'tasks').' t ON t.id = dt.task_id AND t.deleted_at IS NULL'
                .' JOIN '.SqlName::qualify($aimsDatabase, 'task_participants').' tp ON tp.task_id = dt.task_id'
                ." WHERE tp.user_id = ? AND tp.type IN ('leader', 'member') AND tp.rejected_at IS NULL"
                .' AND tp.deleted_at IS NULL AND dt.deleted_at IS NULL))';
            $params[] = (string) $actor['id'];
            $params[] = (string) $actor['id'];
        }

        if ($parts === []) {
            return ['1=0', []];
        }

        return ['('.implode(' OR ', $parts).')', $params];
    }

    /**
     * @param  array{scopes?: list<string>}  $actor
     */
    public static function label(array $actor): string
    {
        $scopes = $actor['scopes'] ?? [];

        if (in_array('all', $scopes, true)) {
            return 'All cases';
        }

        $parts = [];
        if (in_array('org', $scopes, true)) {
            $parts[] = 'cases in the organisation units you are linked to';
        }
        if (in_array('participant', $scopes, true)) {
            $parts[] = 'cases allocated to you';
        }

        return $parts !== [] ? ucfirst(implode(' and ', $parts)) : 'No case access';
    }
}
