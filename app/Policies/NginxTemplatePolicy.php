<?php

namespace App\Policies;

use App\Models\NginxTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NginxTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, NginxTemplate $nginxTemplate): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, NginxTemplate $nginxTemplate): bool
    {
    }

    public function delete(User $user, NginxTemplate $nginxTemplate): bool
    {
    }

    public function restore(User $user, NginxTemplate $nginxTemplate): bool
    {
    }

    public function forceDelete(User $user, NginxTemplate $nginxTemplate): bool
    {
    }
}
