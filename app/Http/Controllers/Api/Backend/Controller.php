<?php

namespace App\Http\Controllers\Api\Backend;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $sortable
     * @return array<string, mixed>
     */
    protected function listFilters(Request $request, array $extra = [], array $sortable = []): array
    {
        $rules = array_merge([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ], $extra);

        if ($sortable !== []) {
            $rules['sort'] = ['sometimes', Rule::in($sortable)];
        }

        $validated = $request->validate($rules);

        if (! array_key_exists('per_page', $validated)) {
            $validated['per_page'] = 25;
        }

        return $validated;
    }

    /**
     * Apply a case-insensitive partial match across an allowlist of columns.
     *
     * Column syntax:
     *  - `column`            local text column, matched with LIKE
     *  - `relation.column`   related text column, matched with LIKE via whereHas
     *  - `#column`           numeric column, matched for equality and only when the term is numeric
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $query
     * @param  list<string>  $columns
     */
    protected function applySearch($query, ?string $term, array $columns): void
    {
        $term = trim((string) $term);

        if ($term === '' || $columns === []) {
            return;
        }

        $like = '%'.addcslashes($term, '\\%_').'%';

        $query->where(function ($group) use ($columns, $like, $term) {
            foreach ($columns as $column) {
                if (str_starts_with($column, '#')) {
                    if (is_numeric($term)) {
                        $group->orWhere(substr($column, 1), $term);
                    }

                    continue;
                }

                if (str_contains($column, '.')) {
                    [$relation, $related] = explode('.', $column, 2);
                    $group->orWhereHas($relation, fn ($q) => $q->where($related, 'like', $like));

                    continue;
                }

                $group->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * Apply the requested sort, falling back to the resource's own default ordering.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applySort($query, array $filters, callable $default): void
    {
        $sort = $filters['sort'] ?? null;

        if ($sort === null) {
            $default($query);

            return;
        }

        $query->orderBy($sort, $filters['direction'] ?? 'asc');

        // Keep pagination stable when the sorted column holds duplicates.
        if ($sort !== 'id') {
            $query->orderBy('id');
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     */
    protected function applyTrashed($query, ?string $trashed): void
    {
        if ($trashed === 'with') {
            $query->withTrashed();
        } elseif ($trashed === 'only') {
            $query->onlyTrashed();
        }
    }
}
