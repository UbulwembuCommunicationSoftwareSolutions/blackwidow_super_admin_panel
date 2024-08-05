<?php

namespace App\Policies;

use App\Models\EnvVariables;
use AppModels\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EnvVariablesPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
     return true;
    }

    public function view(User $user, EnvVariables $envVariables): bool
    {
     return true;
    }

    public function create(User $user): bool
    {
     return true;
    }

    public function update(User $user, EnvVariables $envVariables): bool
    {
     return true;
    }

    public function delete(User $user, EnvVariables $envVariables): bool
    {
     return true;
    }

    public function restore(User $user, EnvVariables $envVariables): bool
    {
     return true;
    }

    public function forceDelete(User $user, EnvVariables $envVariables): bool
    {
     return true;
    }
}
