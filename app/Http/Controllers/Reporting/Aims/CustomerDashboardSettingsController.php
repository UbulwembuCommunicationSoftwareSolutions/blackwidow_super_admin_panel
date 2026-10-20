<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Aims;

use App\Http\Controllers\Api\Backend\Controller;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use App\Services\Reporting\Aims\AimsReportingSettingsStore;
use App\Services\Reporting\Aims\TenantDatabaseException;
use App\Support\CustomerAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerDashboardSettingsController extends Controller
{
    public function __construct(private readonly AimsReportingSettingsStore $settings) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        $denied = $this->guard($request, $row);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        try {
            return response()->json(['data' => $this->settings->read($row)]);
        } catch (TenantDatabaseException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Something went wrong on the server. The details have been logged.'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        $denied = $this->guard($request, $row);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        $validated = $request->validate([
            'settings' => ['present', 'array'],
            'settings.*.key' => ['required', 'string', 'max:80'],
            'settings.*.value' => ['present', 'string', 'max:1000'],
        ]);

        try {
            return response()->json(['data' => $this->settings->write($row, $validated['settings'])]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (TenantDatabaseException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Something went wrong on the server. The details have been logged.'], 500);
        }
    }

    private function guard(Request $request, CustomerDashboard $row): ?JsonResponse
    {
        if (CustomerAdminAccess::isCustomerAdmin($request->user())) {
            return response()->json(['message' => 'Customer admins cannot change report settings.'], 403);
        }

        $this->authorize('update', $row);

        if ($row->dashboard?->handler !== Dashboard::HANDLER_AIMS_CASES) {
            return response()->json(['error' => 'This dashboard has no settings yet.'], 422);
        }

        return null;
    }

    private function find(int $id): CustomerDashboard
    {
        $query = CustomerDashboard::query()->with('dashboard');
        $this->scopeToCustomerAdmin($query);

        return $query->findOrFail($id);
    }
}
