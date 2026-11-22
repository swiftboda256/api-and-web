<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\VehicleType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class VehicleTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VehicleType');
    }

    public function view(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('View:VehicleType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VehicleType');
    }

    public function update(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('Update:VehicleType');
    }

    public function delete(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('Delete:VehicleType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VehicleType');
    }

    public function restore(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('Restore:VehicleType');
    }

    public function forceDelete(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('ForceDelete:VehicleType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VehicleType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VehicleType');
    }

    public function replicate(AuthUser $authUser, VehicleType $vehicleType): bool
    {
        return $authUser->can('Replicate:VehicleType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VehicleType');
    }
}
