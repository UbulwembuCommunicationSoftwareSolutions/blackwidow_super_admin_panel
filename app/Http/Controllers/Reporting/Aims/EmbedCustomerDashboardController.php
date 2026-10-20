<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting\Aims;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AimsEmbedGuard;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\Reporting\Aims\CustomerDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmbedCustomerDashboardController extends Controller
{
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $subscription = $request->attributes->get('customer_subscription');

        if (! $subscription instanceof CustomerSubscription) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $row = CustomerDashboard::query()->with('dashboard')->find($id);

        if ($row === null
            || (int) $row->customer_id !== (int) $subscription->customer_id
            || (int) $row->customer_subscription_id !== (int) $subscription->id) {
            return response()->json(['message' => 'Dashboard not found.'], 404);
        }

        if (! $row->enabled) {
            return response()->json(['message' => 'This dashboard is turned off.'], 403);
        }

        $email = mb_strtolower(trim($validated['email']));
        $user = CustomerUser::query()
            ->where('customer_id', $row->customer_id)
            ->whereRaw('LOWER(email_address) = ?', [$email])
            ->first();

        if ($user === null || ! $row->allowsActor($user)) {
            return response()->json(['message' => 'You do not have access to this dashboard.'], 403);
        }

        $frontend = rtrim((string) config('reporting.frontend_url'), '/');

        if ($frontend === '') {
            return response()->json(['message' => 'The report page is not configured.'], 503);
        }

        $plain = $user->createToken(
            AimsEmbedGuard::NAME,
            ['embed-dashboard:'.$row->id],
            now()->addHours(8),
        )->plainTextToken;

        return response()->json([
            'url' => $frontend.'/embed/dashboards/'.$row->id.'#t='.rawurlencode($plain),
        ]);
    }
}
