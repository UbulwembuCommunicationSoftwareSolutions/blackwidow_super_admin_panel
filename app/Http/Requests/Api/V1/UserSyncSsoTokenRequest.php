<?php

namespace App\Http\Requests\Api\V1;

/**
 * A tenant asking for a fresh hub token for one of its signed-in users, when
 * the token in the shared SSO cookie has been revoked or has gone missing.
 */
class UserSyncSsoTokenRequest extends UserSyncLocateRequest {}
