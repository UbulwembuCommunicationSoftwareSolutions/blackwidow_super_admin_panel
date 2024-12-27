<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NginxTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class NginxTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_nginx::template');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('view_nginx::template');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_nginx::template');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('update_nginx::template');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('delete_nginx::template');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_nginx::template');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('force_delete_nginx::template');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_nginx::template');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('restore_nginx::template');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_nginx::template');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, NginxTemplate $nginxTemplate): bool
    {
        return $user->can('replicate_nginx::template');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_nginx::template');
    }
}
