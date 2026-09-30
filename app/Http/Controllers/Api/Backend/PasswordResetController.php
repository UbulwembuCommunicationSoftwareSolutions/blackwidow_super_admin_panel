<?php

namespace App\Http\Controllers\Api\Backend;

use App\Http\Requests\Api\Backend\ForgotPasswordRequest;
use App\Http\Requests\Api\Backend\ResetPasswordRequest;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CustomerUser;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\FrontendUrl;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Forgot/reset password for the Reseller Console SPA.
 *
 * Panel staff (`User`) are reset here with Laravel's password broker; the link
 * in the email returns them to the SPA's reset page. Customer admins
 * (`CustomerUser`) are owned by their tenant console, so for them we trigger
 * the console's own reset email instead — the same job the panel uses when it
 * creates a console user. Mirrors `AuthController::login`: staff win when the
 * email is shared unless the customer portal is explicitly requested.
 */
class PasswordResetController extends Controller
{
    private const GENERIC_MESSAGE = 'If an account matches that email address, a password reset link has been sent.';

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        if (! $request->isCustomerPortal()) {
            $staff = User::query()->where('email', $email)->first();

            if ($staff) {
                $this->sendStaffResetLink($request, $staff);

                return $this->genericResponse();
            }
        }

        $this->triggerCustomerAdminResetEmails($email);

        return $this->genericResponse();
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }

    /**
     * A failed send is reported, not surfaced: a different response for an
     * existing address would let callers enumerate staff accounts.
     */
    private function sendStaffResetLink(ForgotPasswordRequest $request, User $staff): void
    {
        $expires = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        try {
            Password::broker()->sendResetLink(
                ['email' => $staff->email],
                function (User $user, string $token) use ($request, $expires): void {
                    $user->notify(new ResetPasswordNotification(
                        FrontendUrl::passwordReset($request, $token, $user->email),
                        $expires,
                    ));
                }
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * One email per matching console account: a shared address used by more
     * than one company gets a reset from each of its consoles.
     */
    private function triggerCustomerAdminResetEmails(string $email): void
    {
        CustomerUser::query()
            ->where('email_address', $email)
            ->where('is_system_admin', true)
            ->get()
            ->each(fn (CustomerUser $customerUser) => SendWelcomeEmailJob::dispatch($customerUser));
    }

    private function genericResponse(): JsonResponse
    {
        return response()->json(['message' => self::GENERIC_MESSAGE]);
    }
}
