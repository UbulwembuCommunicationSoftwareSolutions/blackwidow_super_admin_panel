<?php

namespace App\Http\Requests\Api\V1;

/**
 * A tenant asking us to email one of its users a password reset link.
 *
 * Only the user's identity crosses the boundary. The link itself is fetched
 * from the tenant when we send, so a request replayed later cannot carry a
 * stale or forged token.
 */
class UserSyncPasswordResetEmailRequest extends UserSyncLocateRequest {}
