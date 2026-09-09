<?php

namespace App\Http\Controllers\Api\Backend;

use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Role picker source for the admin user form. Unpaginated — there is a
     * handful of roles, and the form needs all of them at once.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return response()->json([
            'data' => Role::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
