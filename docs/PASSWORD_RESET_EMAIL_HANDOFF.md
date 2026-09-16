# Password Reset Email — Move Sending to Super Admin

Planning doc for a follow-up session. Two repos are involved:

- `blackwidow_super_admin_panel` (this repo) — the "super admin panel". Owns `CustomerUser`, decides who has access to what, has already just gained a `CustomerUserObserver` that fires on customer-user creation.
- `blackwidow_cms` (`~/PhpStormProjects/blackwidow_cms`) — one tenant app. Owns its own local `User` model, its own `password_reset_tokens` table, and its own login/reset-password pages.

Do not assume other tenants (firearm-v4, etc.) work like `blackwidow_cms` — this doc only covers console (`blackwidow_cms`). Extending to other systems is explicitly out of scope for this round (see "Decisions already made" below).

---

## 1. Where things stand today

### Already shipped (this session, commit `509b760` in `blackwidow_super_admin_panel`)

- `app/Observers/CustomerUserObserver.php`:
  - `creating()` — forces `console_access = true` on any `CustomerUser` created from the console (i.e. `skip_sync` is falsy), so panel-created users can always log in somewhere.
  - `created()` — for every access flag the new user has, dispatches the matching job (`SendWelcomeEmailJob` for console, `SendSubscriptionEmailJob` for everything else).
- Registered in `app/Providers/AppServiceProvider.php`.
- This replaced equivalent inline logic that used to live in `CustomerUser::boot()`'s `static::created` closure (that closure now only does the tenant push).

### The console (`blackwidow_cms`) email flow, as it exists right now

```
CustomerUserObserver::created()
  → SendWelcomeEmailJob::dispatch($customerUser)   [app/Jobs/SendWelcomeEmailJob.php]
    → CMSService::sendWelcomeEmail($customerUser)  [app/Services/CMSService.php:86-98]
      → POST {tenant_url}/admin-api/send-welcome-email
        body: { "email": customerUser.email_address }
        auth: Bearer {customerSubscription.customer.token}
```

On the `blackwidow_cms` side, that request is handled by:

- Route: `routes/api/api-super-admin.php` → `Route::post('/send-welcome-email', [AdminApiUserController::class, 'sendWelcomeEmail'])` (no route-level middleware — the file wraps everything in `/admin-api` but auth is per-FormRequest).
- Request: `App\Http\Requests\Api\AdminSendWelcomeEmailRequest` extends `AdminSecureTokenRequest`, which authorizes via `$this->bearerToken() === config('settings.secure_token')` — i.e. the tenant's own `SECURE_TOKEN` env value, which **is** `customers.token` from the panel's point of view (same shared secret used both directions — see `docs/TENANT_SYNC_INTEGRATION.md` §2).
- Controller: `App\Http\Controllers\Api\AdminApiUserController::sendWelcomeEmail()` (`app/Http/Controllers/Api/AdminApiUserController.php:84-99`):
  ```php
  public function sendWelcomeEmail(AdminSendWelcomeEmailRequest $request): JsonResponse
  {
      if (! app()->runningUnitTests()) {
          SuperAdminService::importUsers();
      }

      $user = User::where('email', $request->validated('email'))->first();
      if (! $user) {
          return response()->json(['message' => 'User not found'], 404);
      }

      $token = Password::createToken($user);
      $user->sendPasswordResetNotification($token);

      return response()->json(['message' => 'Email sent'], 200);
  }
  ```
  So today, `blackwidow_cms` both **creates** the reset token (has to — it owns the `password_reset_tokens` table and the `User` model that `Password::createToken()` needs) and **sends** the actual email itself, via Laravel's built-in `sendPasswordResetNotification()`.

### Decisions already made (confirmed with the user this session)

1. **Scope**: console only for now. Do not touch firearm-v4 or any other tenant in this round.
2. **Who sends the email**: the super admin panel should become the sender, not `blackwidow_cms`. `blackwidow_cms` still has to *create* the token (only it has the table/model for that), but it should hand the token/link back instead of emailing it itself.
3. **No double emails**: `blackwidow_cms`'s endpoint must stop calling `sendPasswordResetNotification()`. Only the super admin panel emails the user.

### New requirement (added after those decisions, this message)

Add a button to the CMS's own user-management page (`resources/js/Pages/User/Index.vue` in `blackwidow_cms`, an Inertia + Vue page — **not** Blade/Livewire) that lets an admin manually (re)send that same password-reset email for a given user, on demand — not just automatically on creation.

This means there are now **two triggers** that both need to land on "super admin sends a real password-reset email for this console user":

- **Automatic**: `CustomerUserObserver::created()` in this repo (already built), when a new `CustomerUser` has `console_access`.
- **Manual**: an admin clicking a button in `blackwidow_cms`'s user list, for an existing user, any time.

---

## 2. Proposed architecture

Keep one Mailable / one code path on the super-admin side that actually sends the email, and let both triggers feed it — but note the two triggers are on opposite ends, so they need different plumbing to reach that shared path:

```
AUTOMATIC (unchanged direction, payload shape changes)
  CustomerUserObserver::created()
    → SendWelcomeEmailJob → CMSService::sendWelcomeEmail()
      → POST {tenant}/admin-api/send-welcome-email   (existing endpoint, existing auth)
        ← 200 { "token": "...", "reset_url": "https://tenant/reset-password/...?email=..." }
      → super admin builds + sends the actual email itself (new Mailable)

MANUAL (new, reverse direction)
  Admin clicks "Send password reset email" in blackwidow_cms User/Index.vue
    → blackwidow_cms: local controller action generates the token the same way
      AdminApiUserController::sendWelcomeEmail() does today (Password::createToken($user))
    → blackwidow_cms POSTs { email, token/reset_url, app_url } to a NEW endpoint on the
      super admin panel, reusing the existing tenant→super-admin auth already documented
      in docs/TENANT_SYNC_INTEGRATION.md §2 (Bearer {customers.token}, base path
      {SUPERADMIN_API}/api/v1/sync/...)
    → super admin builds + sends the same Mailable
```

Both paths converge on one thing to build in this repo: a small service/Mailable — e.g. `App\Mail\CustomerPasswordResetMail` plus a `CMSService`-adjacent method that takes `(CustomerUser $customerUser, string $resetUrl)` and sends it via `Mail::to(...)->send(...)`. Model it on the existing `AppURLMail` (`app/Mail/AppURLMail.php`) but note that mailable is currently "Welcome to {app}" with just an install link — this new one needs an actual clickable reset-password link, so it's a distinct template/subject, not a reuse of `AppURLMail` as-is.

### Changes needed in `blackwidow_cms`

1. `app/Http/Controllers/Api/AdminApiUserController.php::sendWelcomeEmail()` — stop calling `$user->sendPasswordResetNotification($token)`. Instead return the token and/or a fully-built reset URL as JSON. Need to check what route generates the tenant's actual reset-password page (`routes/web/web-auth.php` has `password.reset`) and build the URL the same way `NewPasswordController`/the stock Laravel reset notification would (so the link the super-admin emails out is identical in shape to what Laravel would have sent).
2. New: a way to trigger this manually from the UI:
   - New route, e.g. `POST /user/{id}/send-password-reset-email` (in `routes/web/web-users.php`, same `auth` middleware group as the rest of `UserController`).
   - New `UserController` method that generates the token (same call as above) and POSTs to the new super-admin endpoint (see below), using the same outbound-auth pattern `SuperAdminService::httpClient()` already uses (`Bearer {SECURE_TOKEN}`, base URL from `config('services.superadmin.api_url')`).
   - New button in `resources/js/Pages/User/Index.vue`, modeled on the existing per-row action buttons (`deactivateUser`/`activateUser`/`archiveUser`/`restoreUser`, lines ~135-174 and ~418-430) — same Tailwind classes, same `router.post(route(...), {}, { preserveState: true, preserveScroll: true, onError: this.showActionError })` pattern, with a `confirm(...)` guard like `archiveUser`/`restoreUser` use.
3. Decide: does the manual button call the super-admin endpoint *synchronously* from the web request (simplest, but ties the request to an outbound HTTP call), or dispatch a queued job (consistent with how `SyncUserToSuperAdminJob` already does tenant→super-admin traffic)? Recommend following the existing job pattern for consistency and to avoid blocking the web request on outbound HTTP.

### Changes needed in `blackwidow_super_admin_panel` (this repo)

1. `app/Services/CMSService.php::sendWelcomeEmail()` — currently just logs the response body. Change to parse the JSON (`token`/`reset_url`), then call the new "actually send it" method instead of assuming the tenant already sent it.
2. New Mailable, e.g. `app/Mail/CustomerPasswordResetMail.php` (model structurally on `app/Mail/AppURLMail.php`, but new view `resources/views/emails/password-reset.blade.php` with a real reset link/button, not an install link).
3. New endpoint for the **manual** trigger from `blackwidow_cms`:
   - Route under the existing canonical group in `routes/api.php` (`Route::middleware('customer.bearer')->prefix('v1/sync')->group(...)`), e.g. `Route::post('users/password-reset-email', [UserSyncController::class, 'sendPasswordResetEmail'])`.
   - New `UserSyncController` method (or a new small controller) that validates `{email, token or reset_url, app_url}`, resolves the `CustomerUser` the same way the existing sync endpoints do, and sends the new Mailable.
   - Needs a matching FormRequest, following the existing `UserSyncPasswordRequest`/`UserSyncLocateRequest` pattern (`app/Http/Requests/...`, exact path TBD — grep `UserSyncUpsertRequest` for the sibling files).
4. Auth for the new endpoint is already solved — reuse `customer.bearer` middleware (`App\Http\Middleware\VerifyCustomerBearerToken`), which resolves the calling tenant via `app_url` in the body and checks `customers.token` — same as every other canonical sync endpoint. No new auth mechanism needed.

---

## 3. Open questions for the next session

- **Reset URL shape**: what does `blackwidow_cms`'s actual password-reset page URL look like (`routes/web/web-auth.php` — check `password.reset` route name and its param signature), so the Mailable built on the super-admin side links somewhere that actually works?
- **Token lifetime**: Laravel's default reset token expiry is 60 minutes (`blackwidow_cms`'s `config/auth.php` — confirm it matches the panel's own `config('auth.passwords.users.expire')`, currently 60). If the manual button is meant to be reusable/idempotent, confirm whether re-clicking invalidates the previous token (Laravel's `Password::createToken()` behavior: yes, it overwrites the existing one for that user).
- **Job vs. sync request** for the manual trigger (see point 3 in "Changes needed in blackwidow_cms" above) — pick one before writing code.
- **Response contract** for the modified `/admin-api/send-welcome-email` — decide exact JSON shape (`token` alone vs. `reset_url` vs. both) so both call sites (automatic + manual) can agree on one payload shape reused by the new super-admin endpoint too.
- **Should the two triggers share one HTTP contract or two?** As scoped above they're mirror images (one hits `blackwidow_cms`'s existing endpoint and reads a token back; the other hits a new `blackwidow_super_admin_panel` endpoint and pushes a token in) — worth sanity-checking that's not more moving parts than needed before implementing both.
- **Rate limiting / abuse**: the manual button lets an admin fire off a real password-reset email on demand — confirm whether that needs throttling beyond what Laravel's password broker already does (`'throttle' => 60` seconds in `config/auth.php`).

---

## 4. Testing checklist for the next session

- `blackwidow_super_admin_panel`: existing tests to keep green — `tests/Feature/Api/Backend/CustomerUserTest.php` (`it dispatches a welcome email job`), `tests/Feature/CanonicalUserSyncTest.php`, `tests/Feature/CustomerBearerTokenAuthTest.php`.
- `blackwidow_cms`: check for existing tests around `AdminApiUserController::sendWelcomeEmail` and `UserController` before changing either — a `grep -rl sendWelcomeEmail tests/` in that repo was not done yet in this session, do that first.
- New coverage needed: the new super-admin endpoint (happy path, wrong/missing bearer token, unknown email), the modified `sendWelcomeEmail` response shape on `blackwidow_cms`, and the new manual-trigger route/button.
