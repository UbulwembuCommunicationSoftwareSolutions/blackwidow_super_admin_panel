<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\CustomerUser;
use App\Models\User;
use App\Services\LmsImpersonationService;
use App\Services\UserSync\TenantUserPusher;
use App\Support\CustomerSync\LmsHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Staff open the LMS admin as an LMS admin user; customer admins open the
 * LMS member portal as the member linked to their customer user.
 */
class LmsAccessController extends Controller
{
    public function __invoke(Request $request, LmsImpersonationService $lms, TenantUserPusher $pusher): JsonResponse
    {
        if (LmsHub::configuredUrl() === null || blank(config('services.lms.sync_token'))) {
            return response()->json(['message' => 'The LMS is not configured on this Super Admin.'], 503);
        }

        $actor = $request->user();

        if ($actor instanceof CustomerUser) {
            return $this->memberLink($actor, $lms, $pusher);
        }

        if ($actor instanceof User) {
            return $this->staffLink($actor, $lms);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    private function staffLink(User $user, LmsImpersonationService $lms): JsonResponse
    {
        try {
            return response()->json($lms->issueStaffLink($user));
        } catch (\Throwable $e) {
            Log::warning('LMS staff access failed', ['staff_user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The LMS could not sign you in as an admin. Please try again.'], 502);
        }
    }

    private function memberLink(CustomerUser $user, LmsImpersonationService $lms, TenantUserPusher $pusher): JsonResponse
    {
        if ($user->isDeleteScheduled()) {
            return response()->json(['message' => 'Your account has been archived.'], 403);
        }

        try {
            $pusher->upsertToLmsHub($user);
        } catch (\Throwable $e) {
            Log::warning('LMS member push before access failed', ['customer_user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        try {
            return response()->json($lms->issueLink($user));
        } catch (\Throwable $e) {
            Log::warning('LMS member access failed', ['customer_user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Your account is not set up on the LMS yet. Please try again shortly or contact support.'], 502);
        }
    }
}
