<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerSyncIndexRequest;
use App\Http\Requests\Api\V1\UserSyncIndexRequest;
use App\Http\Requests\Api\V1\UserSyncLocateRequest;
use App\Http\Requests\Api\V1\UserSyncPasswordRequest;
use App\Http\Requests\Api\V1\UserSyncPasswordResetEmailRequest;
use App\Http\Requests\Api\V1\UserSyncUpsertRequest;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CustomerUser;
use App\Services\UserSync\CustomerUserSyncService;
use App\Support\UserSync\SyncOutcome;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * The canonical sync surface for tenant apps.
 *
 * Every response returns this panel's authoritative copy of the record in the
 * canonical shape, so a caller whose write lost a conflict can simply adopt what
 * it gets back instead of needing a second round trip.
 */
class UserSyncController extends Controller
{
    public function __construct(private readonly CustomerUserSyncService $sync) {}

    /**
     * Full set of users for the tenant, for periodic reconciliation.
     */
    public function index(UserSyncIndexRequest $request): JsonResponse
    {
        $users = CustomerUser::withTrashed()
            ->where('customer_id', $request->subscription()->customer_id)
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users->map(fn (CustomerUser $user) => $this->serialize($user, includePasswordHash: true))->all(),
        ]);
    }

    /**
     * All LMS-eligible users across customers, for the shared hub reconcile.
     */
    public function hubIndex(CustomerSyncIndexRequest $request): JsonResponse
    {
        $users = CustomerUser::withTrashed()
            ->where(function ($query): void {
                $query->where('lms_access', true)
                    ->orWhere('is_system_admin', true);
            })
            ->orderBy('id')
            ->get();

        Log::debug('sync: user hub list', [
            'app_url' => $request->input('app_url'),
            'count' => $users->count(),
            'emails' => $users->pluck('email_address')->all(),
        ]);

        return response()->json([
            'success' => true,
            'users' => $users->map(fn (CustomerUser $user) => $this->serialize($user, includePasswordHash: true))->all(),
        ]);
    }

    public function upsert(UserSyncUpsertRequest $request): JsonResponse
    {
        $result = $this->sync->upsertFromTenant(
            $request->subscription(),
            $request->payload(),
            $request->cleartextPassword(),
        );

        return response()->json([
            'success' => true,
            'outcome' => $result['outcome']->value,
            'user' => $this->serialize($result['user']),
        ], $result['outcome'] === SyncOutcome::Created ? 201 : 200);
    }

    public function archive(UserSyncLocateRequest $request): JsonResponse
    {
        $user = $this->sync->archiveFromTenant($request->subscription(), $request->payload());

        return $user
            ? $this->ok(SyncOutcome::Archived, $user)
            : $this->notFound();
    }

    public function restore(UserSyncLocateRequest $request): JsonResponse
    {
        $user = $this->sync->restoreFromTenant($request->subscription(), $request->payload());

        return $user
            ? $this->ok(SyncOutcome::Restored, $user)
            : $this->notFound();
    }

    public function password(UserSyncPasswordRequest $request): JsonResponse
    {
        $user = $this->sync->setPasswordFromTenant(
            $request->subscription(),
            $request->payload(),
            $request->cleartextPassword(),
        );

        return $user
            ? $this->ok(SyncOutcome::Updated, $user)
            : $this->notFound();
    }

    /**
     * Email the user a password reset link, on a tenant's behalf.
     *
     * The same job the panel uses when it creates a console user, so a manual
     * resend from a tenant's own UI produces exactly the same email.
     */
    public function passwordResetEmail(UserSyncPasswordResetEmailRequest $request): JsonResponse
    {
        $user = $this->sync->locate($request->subscription(), $request->payload());

        if (! $user) {
            return $this->notFound();
        }

        SendWelcomeEmailJob::dispatch($user);

        return response()->json([
            'success' => true,
            'message' => 'Password reset email queued.',
            'user' => $this->serialize($user),
        ], 202);
    }

    private function ok(SyncOutcome $outcome, CustomerUser $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'outcome' => $outcome->value,
            'user' => $this->serialize($user),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'User not found for this customer.',
        ], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomerUser $user, bool $includePasswordHash = false): array
    {
        $data = UserSyncPayload::fromCustomerUser($user)->toArray();

        // Only the reconcile listing carries the hash, so a tenant can seed
        // credentials for a user it has never seen. Never on a write response.
        if ($includePasswordHash) {
            $data['password_hash'] = $user->password;
        }

        return $data;
    }
}
