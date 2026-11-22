<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceCatalog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ServiceCatalogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ServiceCatalog');
    }

    public function view(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('View:ServiceCatalog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ServiceCatalog');
    }

    public function update(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('Update:ServiceCatalog');
    }

    public function delete(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('Delete:ServiceCatalog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ServiceCatalog');
    }

    public function restore(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('Restore:ServiceCatalog');
    }

    public function forceDelete(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('ForceDelete:ServiceCatalog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ServiceCatalog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ServiceCatalog');
    }

    public function replicate(AuthUser $authUser, ServiceCatalog $serviceCatalog): bool
    {
        return $authUser->can('Replicate:ServiceCatalog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ServiceCatalog');
    }
}
