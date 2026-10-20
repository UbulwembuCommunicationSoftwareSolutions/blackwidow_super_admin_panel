<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Aims;

use App\Http\Controllers\Api\Backend\Controller;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use App\Services\Reporting\Aims\AimsFilters;
use App\Services\Reporting\Aims\AimsReportService;
use App\Services\Reporting\Aims\TenantConnectionFactory;
use App\Services\Reporting\Aims\TenantDatabaseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DashboardReportController extends Controller
{
    public function __construct(private readonly TenantConnectionFactory $connections) {}

    public function me(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor, CustomerDashboard $row) {
            return response()->json($reports->profile($actor, (string) $row->dashboard?->name));
        });
    }

    public function options(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['dashboard'] && ! $actor['can']['cases']) {
                return $this->deny($reports, $actor, 'options', $request);
            }

            return response()->json($reports->filterOptions($actor));
        });
    }

    public function dashboard(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['dashboard']) {
                return $this->deny($reports, $actor, 'dashboard', $request);
            }

            $filters = AimsFilters::clean($request->query());
            $grain = in_array($request->query('grain'), ['year', 'quarter', 'month'], true)
                ? (string) $request->query('grain')
                : 'year';
            $started = microtime(true);
            $data = $reports->dashboard($actor, $filters, $grain);
            $data['ms'] = (int) round((microtime(true) - $started) * 1000);
            $reports->logAccess($actor, 'dashboard', $this->filterDetail($filters), $request->ip());

            return response()->json($data);
        });
    }

    public function cases(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['cases']) {
                return $this->deny($reports, $actor, 'cases', $request);
            }

            $filters = AimsFilters::clean($request->query());
            $data = $reports->caseList(
                $actor,
                $filters,
                (int) $request->query('page', 1),
                (int) $request->query('per', 25),
                (string) $request->query('sort', 'received'),
                (string) $request->query('dir', 'desc'),
            );
            $reports->logAccess($actor, 'case_list', $this->filterDetail($filters), $request->ip());

            return response()->json($data);
        });
    }

    public function caseDetail(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['cases']) {
                return $this->deny($reports, $actor, 'case', $request);
            }

            $caseId = (int) $request->query('id', 0);
            $case = $caseId > 0 ? $reports->caseDetail($actor, $caseId) : null;
            if (! $case) {
                $reports->logAccess($actor, 'case_not_found', 'id '.$caseId, $request->ip());

                return response()->json(['error' => 'Case not found, or outside your access.'], 404);
            }

            $reports->logAccess($actor, 'case_view', (string) ($case['reference'] ?? ''), $request->ip());

            return response()->json($case);
        });
    }

    public function export(Request $request, int $id): JsonResponse|StreamedResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['cases'] || ! $actor['can']['export']) {
                return $this->deny($reports, $actor, 'export', $request);
            }

            $filters = AimsFilters::clean($request->all());
            $rows = $reports->caseExport($actor, $filters);
            $reports->logAccess($actor, 'export', count($rows).' rows '.$this->filterDetail($filters), $request->ip());

            $filename = 'aims_cases_'.AimsReportService::now()->format('Y-m-d_His').'.csv';
            $columns = [
                'Reference', 'Received', 'Financial year', 'Classification (provisional)', 'Division', 'Directorate', 'Department',
                'Type of allegation', 'Source of allegation', 'Categories', 'Sub-categories', 'Status', 'Status group', 'Priority',
                'Investigators', 'Complainants', 'Due date', 'Closed', 'Days open', 'Working hours', 'Overdue', 'Root cause', 'Outcome tags',
            ];

            return response()->streamDownload(function () use ($rows, $columns) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fwrite($out, $this->csvLine($columns));
                foreach ($rows as $row) {
                    fwrite($out, $this->csvLine(array_values($row)));
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
        });
    }

    public function audit(Request $request, int $id): JsonResponse
    {
        return $this->run($request, $id, function (AimsReportService $reports, array $actor) use ($request) {
            if (! $actor['can']['audit']) {
                return $this->deny($reports, $actor, 'audit', $request);
            }

            $data = $reports->auditList($request->query(), (int) $request->query('page', 1));
            $reports->logAccess($actor, 'audit_view', '', $request->ip());

            return response()->json($data);
        });
    }

    /**
     * @param  callable(AimsReportService, array<string, mixed>, CustomerDashboard): JsonResponse|StreamedResponse  $callback
     */
    private function run(Request $request, int $id, callable $callback): JsonResponse|StreamedResponse
    {
        $query = CustomerDashboard::query()->with('dashboard');
        $this->scopeToCustomerAdmin($query);
        $row = $query->findOrFail($id);
        $this->authorize('view', $row);

        if (! $row->enabled) {
            return response()->json(['error' => 'This dashboard is turned off.'], 403);
        }

        if ($row->dashboard?->handler !== Dashboard::HANDLER_AIMS_CASES) {
            return response()->json(['error' => 'This dashboard has no report handler yet.'], 422);
        }

        try {
            $connection = $this->connections->connect($row);
            $reports = new AimsReportService($connection);
            $actor = $reports->actorFor($request->user());

            return $callback($reports, $actor, $row);
        } catch (TenantDatabaseException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Something went wrong on the server. The details have been logged.'], 500);
        } finally {
            $this->connections->disconnect();
        }
    }

    /**
     * @param  array<string, mixed>  $actor
     */
    private function deny(AimsReportService $reports, array $actor, string $what, Request $request): JsonResponse
    {
        $reports->logAccess($actor, 'denied', $what, $request->ip());

        return response()->json(['error' => 'Your role does not give access to this.'], 403);
    }

    /**
     * RFC 4180 row. A leading = + - @ tab or return is prefixed so a spreadsheet cannot treat it as a formula.
     *
     * @param  list<mixed>  $values
     */
    private function csvLine(array $values): string
    {
        $cells = array_map(function ($value) {
            $text = (string) ($value ?? '');
            if (preg_match('/^[=+\-@\t\r]/', $text)) {
                $text = "'".$text;
            }

            return '"'.str_replace('"', '""', $text).'"';
        }, $values);

        return implode(',', $cells)."\n";
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filterDetail(array $filters): string
    {
        if ($filters === []) {
            return '';
        }

        return (string) json_encode($filters, JSON_UNESCAPED_UNICODE);
    }
}
