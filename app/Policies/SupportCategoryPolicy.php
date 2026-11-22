<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupportCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SupportCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SupportCategory');
    }

    public function view(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('View:SupportCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SupportCategory');
    }

    public function update(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('Update:SupportCategory');
    }

    public function delete(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('Delete:SupportCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SupportCategory');
    }

    public function restore(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('Restore:SupportCategory');
    }

    public function forceDelete(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('ForceDelete:SupportCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SupportCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SupportCategory');
    }

    public function replicate(AuthUser $authUser, SupportCategory $supportCategory): bool
    {
        return $authUser->can('Replicate:SupportCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SupportCategory');
    }
}
