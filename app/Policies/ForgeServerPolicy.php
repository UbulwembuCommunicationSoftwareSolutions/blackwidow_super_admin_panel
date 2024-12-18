<?php

namespace App\Policies;

use App\Models\ForgeServer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ForgeServerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, ForgeServer $forgeServer): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, ForgeServer $forgeServer): bool
    {
    }

    public function delete(User $user, ForgeServer $forgeServer): bool
    {
    }

    public function restore(User $user, ForgeServer $forgeServer): bool
    {
    }

    public function forceDelete(User $user, ForgeServer $forgeServer): bool
    {
    }
}
