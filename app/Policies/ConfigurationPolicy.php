<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Configuration;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConfigurationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Configuration');
    }

    public function view(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('View:Configuration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Configuration');
    }

    public function update(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('Update:Configuration');
    }

    public function delete(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('Delete:Configuration');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Configuration');
    }

    public function restore(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('Restore:Configuration');
    }

    public function forceDelete(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('ForceDelete:Configuration');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Configuration');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Configuration');
    }

    public function replicate(AuthUser $authUser, Configuration $configuration): bool
    {
        return $authUser->can('Replicate:Configuration');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Configuration');
    }

}