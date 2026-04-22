<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\TemplateEnvVariables;
use Illuminate\Auth\Access\HandlesAuthorization;

class TemplateEnvVariablesPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TemplateEnvVariables');
    }

    public function view(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('View:TemplateEnvVariables');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TemplateEnvVariables');
    }

    public function update(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('Update:TemplateEnvVariables');
    }

    public function delete(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('Delete:TemplateEnvVariables');
    }

    public function restore(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('Restore:TemplateEnvVariables');
    }

    public function forceDelete(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('ForceDelete:TemplateEnvVariables');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TemplateEnvVariables');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TemplateEnvVariables');
    }

    public function replicate(AuthUser $authUser, TemplateEnvVariables $templateEnvVariables): bool
    {
        return $authUser->can('Replicate:TemplateEnvVariables');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TemplateEnvVariables');
    }
}
