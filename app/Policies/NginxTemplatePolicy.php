<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\NginxTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class NginxTemplatePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:NginxTemplate');
    }

    public function view(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('View:NginxTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:NginxTemplate');
    }

    public function update(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('Update:NginxTemplate');
    }

    public function delete(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('Delete:NginxTemplate');
    }

    public function restore(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('Restore:NginxTemplate');
    }

    public function forceDelete(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('ForceDelete:NginxTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:NginxTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:NginxTemplate');
    }

    public function replicate(AuthUser $authUser, NginxTemplate $nginxTemplate): bool
    {
        return $authUser->can('Replicate:NginxTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:NginxTemplate');
    }

}