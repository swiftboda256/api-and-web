<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VehicleModel;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VehicleModelPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VehicleModel');
    }

    public function view(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('View:VehicleModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VehicleModel');
    }

    public function update(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('Update:VehicleModel');
    }

    public function delete(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('Delete:VehicleModel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VehicleModel');
    }

    public function restore(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('Restore:VehicleModel');
    }

    public function forceDelete(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('ForceDelete:VehicleModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VehicleModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VehicleModel');
    }

    public function replicate(AuthUser $authUser, VehicleModel $vehicleModel): bool
    {
        return $authUser->can('Replicate:VehicleModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VehicleModel');
    }
}
