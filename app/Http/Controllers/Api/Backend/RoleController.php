<?php

namespace App\Http\Controllers\Api\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Role picker source for the admin user form. Unpaginated — there is a
     * handful of roles, and the form needs all of them at once.
     *
     * Gated on the screen it serves rather than on `ViewAny:Role`. Shield
     * generates permissions for the twelve resource models only, so no
     * environment has a `*:Role` permission and requiring one left the picker
     * dead everywhere. `ViewAny:Role` is still honoured if it is ever granted.
     *
     * This exposes nothing new: `GET /users` already returns each user's roles
     * by name, so anyone who can list admin users can already see them.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can('ViewAny:User') || $user->can('ViewAny:Role'), 403);

        return response()->json([
            'data' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
