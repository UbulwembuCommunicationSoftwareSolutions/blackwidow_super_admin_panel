<?php

namespace App\Http\Controllers\Api\Backend;

use App\Models\CustomerUser;
use App\Models\User;
use App\Support\CustomerAdminAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'portal' => ['sometimes', 'in:customer'],
        ]);

        if (($validated['portal'] ?? null) === 'customer') {
            return $this->tokenResponse($this->authenticateCustomerAdmin(
                $validated['email'],
                $validated['password'],
            ));
        }

        $staff = User::query()->where('email', $validated['email'])->first();

        if ($staff) {
            if (! Hash::check($validated['password'], $staff->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            return $this->tokenResponse($staff);
        }

        return $this->tokenResponse($this->authenticateCustomerAdmin(
            $validated['email'],
            $validated['password'],
        ));
    }

    /**
     * Sign in a Super Admin customer user. Ignores panel staff who share the email.
     */
    private function authenticateCustomerAdmin(string $email, string $password): CustomerUser
    {
        $matches = CustomerUser::query()
            ->where('email_address', $email)
            ->where('is_system_admin', true)
            ->get()
            ->filter(fn (CustomerUser $user) => Hash::check($password, $user->password))
            ->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'email' => ['This email is used by more than one company. Those accounts need different passwords.'],
            ]);
        }

        $customerUser = $matches->first();

        if (! $customerUser instanceof CustomerUser) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $customerUser;
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof CustomerUser && ! $user->is_system_admin) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    private function tokenResponse(Authenticatable $user): JsonResponse
    {
        $token = $user->createToken('backend', ['backend'])->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(Authenticatable $user): array
    {
        if ($user instanceof CustomerUser) {
            return [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email_address,
                'email_verified_at' => null,
                'roles' => ['customer_admin'],
                'customer_id' => $user->customer_id,
                'actor_type' => 'customer_admin',
                'permissions' => CustomerAdminAccess::permissions(),
            ];
        }

        /** @var User $user */
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
