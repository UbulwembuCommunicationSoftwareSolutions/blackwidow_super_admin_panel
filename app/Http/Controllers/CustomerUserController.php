<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerUserResource;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Services\UserSync\CustomerUserSyncService;
use App\Support\UserSync\SyncOutcome;
use App\Support\UserSync\TenantResolver;
use App\Support\UserSync\UserSyncPayload;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Log;

/**
 * Legacy single-purpose sync endpoints, kept so tenant apps that have not yet
 * deployed the canonical contract keep working.
 *
 * These are adapters only: they translate their older request and response
 * shapes to and from the canonical payload and delegate every decision to
 * CustomerUserSyncService, which is the one place inbound writes are applied.
 * New work belongs on Api\V1\UserSyncController.
 */
class CustomerUserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CustomerUserSyncService $sync) {}

    private function findCustomerSubscriptionByUrl(?string $appUrl): ?CustomerSubscription
    {
        return TenantResolver::resolveSubscription($appUrl);
    }

    /**
     * The response shape these older endpoints have always returned.
     *
     * @return array<string, mixed>
     */
    private function legacyUserPayload(CustomerUser $user, bool $includePassword = true): array
    {
        $payload = [
            'id' => $user->id,
            'cms_user_id' => $user->cms_user_id,
            'email_address' => $user->email_address,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'cellphone' => $user->cellphone,
        ];

        if ($includePassword) {
            $payload['password'] = $user->password;
        }

        foreach (array_keys(UserSyncPayload::ACCESS_FLAGS) as $flag) {
            $payload[$flag] = $user->{$flag} ? 1 : 0;
        }

        return $payload + [
            'is_system_admin' => $user->is_system_admin ? 1 : 0,
            'delete_scheduled' => $user->delete_scheduled?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /**
     * Build a canonical payload from the flat field names the legacy endpoints use.
     *
     * @param  array<string, mixed>  $data
     */
    private function payloadFromLegacyRequest(array $data): UserSyncPayload
    {
        $canonical = $data;
        $canonical['super_admin_user_id'] = $data['super_admin_user_id'] ?? null;

        // 'active' predates console_access and meant the same thing.
        if (! array_key_exists('console_access', $canonical) && array_key_exists('active', $data)) {
            $canonical['console_access'] = $data['active'];
        }

        if (array_key_exists('cms_updated_at', $data)) {
            $canonical['updated_at'] = $data['cms_updated_at'];
        }

        return UserSyncPayload::fromArray($canonical);
    }

    public function index(Request $request)
    {
        $url = $request->get('app_url');
        $customerSubscription = $this->findCustomerSubscriptionByUrl($url);

        if (! $customerSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $users = CustomerUser::withTrashed()
            ->where('customer_id', $customerSubscription->customer_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(fn (CustomerUser $user) => $this->legacyUserPayload($user))->all(),
        ]);
    }

    public function login(Request $request)
    {
        $input = $request->all();
        Log::info('Login request: '.json_encode($input));
        if ($request->has('email')) {
            $email = $request->get('email');
        } else {
            $email = null;
        }
        if ($request->has('cellphone')) {
            $cellphone = $request->get('cellphone');
        } else {
            $cellphone = null;
        }
        $password = $request->get('password');
        $url = $request->get('app_url');
        // IF URL IS HTTP, REPLACE WITH HTTPS
        if (strpos($url, 'http://') === 0) {
            $url = str_replace('http://', 'https://', $url);
        }
        $customerSubscription = CustomerSubscription::where('url', $url)->first();
        if (! $customerSubscription) {
            Log::info(CustomerSubscription::where('url', $url)->toRawSql());
            Log::info('Customer Subscription not found');

            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        Log::info('Customer Subscription Found: '.$customerSubscription->url);
        $customerUser = null;
        if ($email) {
            $customerUser = CustomerUser::where('customer_id', $customerSubscription->customer_id)->where('email_address', $email)->first();
        }
        if ($cellphone) {
            $customerUser = CustomerUser::where('customer_id', $customerSubscription->customer_id)->where('cellphone', $cellphone)->first();
        }
        if (! $customerUser) {
            Log::info('Customer User not found');

            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if ($customerUser->isDeleteScheduled()) {
            Log::info('Login rejected: user is scheduled for deletion: '.$customerUser->email_address);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if ($customerUser) {
            if (! $this->checkAccess($customerUser, $customerSubscription)) {
                Log::info('Access Denied for user: '.$customerUser->email_address);

                return response()->json(
                    [
                        'message' => 'Access Denied',
                        'customer_user' => $customerUser,
                    ],
                    401
                );
            }
        }
        Log::info('Stored hash: '.$customerUser->password);
        Log::info('Entered password: '.$request->password);
        Log::info('Hash Check: '.(Hash::check($request->password, $customerUser->password) ? 'Match' : 'No Match'));
        if (! \Hash::check($request->password, $customerUser->password)) {
            return response()->json(
                [
                    'debug' => $request->password.' is not equal to '.$customerUser->password,
                    'message' => 'Invalid credentials',
                    'customer_user' => $customerUser,
                ],
                401
            );
        }

        //        if(!$this->checkAccess($customerUser,$customerSubscription)){
        //            return response()->json(
        //                [
        //                    'message' => 'Access Denied',
        //                    'customer_user' => $customerUser,
        //                ], 401
        //            );
        //        }

        // Create a new Sanctum token
        $token = $customerUser->createToken('customer-user-token')->plainTextToken;

        $customerSubscriptions = CustomerSubscription::where('customer_id', $customerUser->customer_id)->get();

        return response()->json([
            'message' => 'Login successful',
            'user' => $customerUser,
            'token' => $token,
        ]);
    }

    public function checkAccess(CustomerUser $user, CustomerSubscription $subscription)
    {
        Log::info('Checking if user '.$user->cellphone.' has access to subscription '.$subscription->url);
        if ((int) $subscription->subscription_type_id == 1) {
            if ($user->console_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 2) {
            if ($user->firearm_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 3) {
            if ($user->responder_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 4) {
            if ($user->reporter_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 5) {
            if ($user->security_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 6) {
            if ($user->driver_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 7) {
            if ($user->survey_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 9) {
            if ($user->time_and_attendance_access) {
                return true;
            } else {
                return false;
            }
        }
        if ((int) $subscription->subscription_type_id == 10) {
            if ($user->stock_access) {
                return true;
            } else {
                return false;
            }
        }

        return false;
    }

    public function setAccess(CustomerUser $user, CustomerSubscription $subscription)
    {
        Log::info('Setting access for user '.$user->cellphone.' to subscription '.$subscription->url);

        switch ((int) $subscription->subscription_type_id) {
            case 1:
                $user->console_access = true;
                break;
            case 2:
                $user->firearm_access = true;
                break;
            case 3:
                $user->responder_access = true;
                break;
            case 4:
                $user->reporter_access = true;
                break;
            case 5:
                $user->security_access = true;
                break;
            case 6:
                $user->driver_access = true;
                break;
            case 7:
                $user->survey_access = true;
                break;
            case 9:
                $user->time_and_attendance_access = true;
                break;
            case 10:
                $user->stock_access = true;
                break;
            default:
                Log::warning('Unknown subscription type ID: '.$subscription->subscription_type_id);

                return false;
        }

        $user->save();
        Log::info('Access granted for user '.$user->email_address.' to subscription type '.$subscription->subscription_type_id);

        return true;
    }

    public function store(Request $request)
    {
        Log::info(json_encode($request->all()));

        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'subscription_id' => 'nullable', // This field is ignored, we only use app_url
            'password' => 'required|string',
            'user' => 'required|array',
            'user.cms_user_id' => 'nullable|integer',
            'user.first_name' => 'required|string',
            'user.last_name' => 'nullable|string',
            'user.email' => 'required|email',
            'user.cellphone' => 'nullable|string',
            'user.active' => 'nullable|boolean',
            'user.console_access' => 'nullable|boolean',
            'user.firearm_access' => 'nullable|boolean',
            'user.responder_access' => 'nullable|boolean',
            'user.reporter_access' => 'nullable|boolean',
            'user.security_access' => 'nullable|boolean',
            'user.driver_access' => 'nullable|boolean',
            'user.survey_access' => 'nullable|boolean',
            'user.time_and_attendance_access' => 'nullable|boolean',
            'user.stock_access' => 'nullable|boolean',
            'user.is_system_admin' => 'nullable|boolean',
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            Log::error('Customer subscription not found for app_url: '.$validated['app_url']);

            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $payload = $this->payloadFromLegacyRequest($validated['user']);

        // This endpoint means "create", so an existing live user is a conflict
        // rather than something to overwrite. A tombstoned one is resurrected.
        $existing = $this->sync->locate($customerSub, $payload);

        if ($existing && ! $existing->trashed() && $existing->delete_scheduled === null) {
            Log::info('User already exists with email: '.$payload->email);

            return response()->json([
                'success' => false,
                'message' => 'Email already exists',
                'errors' => [
                    'email' => ['The email has already been taken.'],
                ],
            ], 422);
        }

        try {
            $result = $this->sync->upsertFromTenant($customerSub, $payload, $validated['password']);
        } catch (\Exception $e) {
            Log::error('Failed to create user: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $this->legacyUserPayload($result['user']),
        ]);
    }

    public function show(CustomerUser $customerUser)
    {
        $this->authorize('view', $customerUser);

        return new CustomerUserResource($customerUser);
    }

    public function update(Request $request, CustomerUser $customerUser)
    {
        $this->authorize('update', $customerUser);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers'],
            'email_address' => ['required'],
            'password' => ['required'],
            'first_name' => ['required'],
            'last_name' => ['required'],
        ]);

        $customerUser->update($data);

        return new CustomerUserResource($customerUser);
    }

    public function destroy(CustomerUser $customerUser)
    {
        $this->authorize('delete', $customerUser);

        $customerUser->scheduleDelete();

        return response()->json();
    }

    public function archiveUser(Request $request)
    {
        $validated = $request->validate([
            'app_url' => 'required|string',
            'email' => 'nullable|email',
            'super_admin_user_id' => 'nullable|integer',
        ]);

        if (blank($validated['email'] ?? null) && blank($validated['super_admin_user_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'email or super_admin_user_id is required',
            ], 422);
        }

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $customerUser = $this->sync->archiveFromTenant(
            $customerSub,
            $this->payloadFromLegacyRequest($validated),
        );

        if (! $customerUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User archived successfully',
            'delete_scheduled' => $customerUser->delete_scheduled?->toISOString(),
        ]);
    }

    public function restoreUser(Request $request)
    {
        $validated = $request->validate([
            'app_url' => 'required|string',
            'email' => 'nullable|email',
            'super_admin_user_id' => 'nullable|integer',
        ]);

        if (blank($validated['email'] ?? null) && blank($validated['super_admin_user_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'email or super_admin_user_id is required',
            ], 422);
        }

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $customerUser = $this->sync->restoreFromTenant(
            $customerSub,
            $this->payloadFromLegacyRequest($validated),
        );

        if (! $customerUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'user' => [
                'id' => $customerUser->id,
                'email_address' => $customerUser->email_address,
                'delete_scheduled' => $customerUser->delete_scheduled?->toISOString(),
            ],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'app_url' => 'required|string',
            'password' => 'required|string',
            'super_admin_user_id' => 'nullable|integer',
            'email' => 'nullable|email',
            'cellphone' => 'nullable|string',
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json(['message' => 'Invalid app URL'], 400);
        }

        $customerUser = $this->sync->setPasswordFromTenant(
            $customerSub,
            $this->payloadFromLegacyRequest($validated),
            $validated['password'],
        );

        if (! $customerUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function deactivateUser(Request $request)
    {
        return $this->setConsoleAccess($request, false);
    }

    public function activateUser(Request $request)
    {
        return $this->setConsoleAccess($request, true);
    }

    /**
     * The tenant's "active" toggle maps to console access for that tenant only.
     * It deliberately does not touch the other product flags: those are separate
     * entitlements and blanket-granting them was how users ended up with access
     * to products the customer had not subscribed them to.
     */
    private function setConsoleAccess(Request $request, bool $hasAccess)
    {
        $validated = $request->validate([
            'app_url' => 'required|string',
            'email' => 'required|email',
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json(['message' => 'Invalid app URL'], 400);
        }

        $customerUser = CustomerUser::where('email_address', $validated['email'])
            ->where('customer_id', $customerSub->customer_id)
            ->first();

        if (! $customerUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $customerUser->skip_sync = true;
        $customerUser->console_access = $hasAccess;
        $customerUser->save();

        return response()->json([
            'message' => $hasAccess ? 'User Activated Successfully' : 'User Deactivated Successfully',
        ]);
    }

    /**
     * Update user from CMS with conflict resolution
     */
    public function updateFromCMS(Request $request)
    {
        Log::info('Update user from CMS: '.json_encode($request->all()));

        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
            'cms_user_id' => 'nullable|integer',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'cellphone' => 'nullable|string',
            'password' => 'nullable|string|min:6', // Optional cleartext password
            'console_access' => 'nullable|boolean',
            'firearm_access' => 'nullable|boolean',
            'responder_access' => 'nullable|boolean',
            'reporter_access' => 'nullable|boolean',
            'security_access' => 'nullable|boolean',
            'driver_access' => 'nullable|boolean',
            'survey_access' => 'nullable|boolean',
            'time_and_attendance_access' => 'nullable|boolean',
            'stock_access' => 'nullable|boolean',
            'is_system_admin' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'cms_updated_at' => 'nullable|date', // For conflict resolution
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $payload = $this->payloadFromLegacyRequest($validated);

        if (! $this->sync->locate($customerSub, $payload)) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: '.$validated['super_admin_user_id'],
            ], 404);
        }

        $result = $this->sync->upsertFromTenant($customerSub, $payload, $validated['password'] ?? null);

        $message = $result['outcome'] === SyncOutcome::Stale
            ? 'User updated successfully (conflict resolved - SuperAdmin version is newer)'
            : 'User updated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $this->legacyUserPayload($result['user']),
        ]);
    }

    /**
     * Get single user by super_admin_user_id
     */
    public function getSingleUser(Request $request)
    {
        Log::info('Get single user: '.json_encode($request->all()));

        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $user = $this->sync->locate($customerSub, $this->payloadFromLegacyRequest($validated));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: '.$validated['super_admin_user_id'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => $this->legacyUserPayload($user),
        ]);
    }

    /**
     * Update user password from CMS (receives plain text password and hashes it)
     */
    public function updatePasswordFromCMS(Request $request)
    {
        Log::info('Update password from CMS: '.json_encode($request->all()));

        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email',
            'cellphone' => 'nullable|string',
        ]);

        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (! $customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        $user = $this->sync->setPasswordFromTenant(
            $customerSub,
            $this->payloadFromLegacyRequest($validated),
            $validated['password'],
        );

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: '.$validated['super_admin_user_id'],
            ], 404);
        }

        Log::info('Password updated successfully for user: '.$user->email_address);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
            'user' => [
                'id' => $user->id,
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'updated_at' => $user->updated_at->toISOString(),
            ],
        ]);
    }
}
