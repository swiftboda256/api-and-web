<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SurgePricingSchedule;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SurgePricingSchedulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SurgePricingSchedule');
    }

    public function view(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('View:SurgePricingSchedule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SurgePricingSchedule');
    }

    public function update(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('Update:SurgePricingSchedule');
    }

    public function delete(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('Delete:SurgePricingSchedule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SurgePricingSchedule');
    }

    public function restore(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('Restore:SurgePricingSchedule');
    }

    public function forceDelete(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('ForceDelete:SurgePricingSchedule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SurgePricingSchedule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SurgePricingSchedule');
    }

    public function replicate(AuthUser $authUser, SurgePricingSchedule $surgePricingSchedule): bool
    {
        return $authUser->can('Replicate:SurgePricingSchedule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SurgePricingSchedule');
    }
}
