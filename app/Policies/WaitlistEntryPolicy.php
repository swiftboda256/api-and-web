<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WaitlistEntry;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WaitlistEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WaitlistEntry');
    }

    public function view(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('View:WaitlistEntry');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WaitlistEntry');
    }

    public function update(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('Update:WaitlistEntry');
    }

    public function delete(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('Delete:WaitlistEntry');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WaitlistEntry');
    }

    public function restore(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('Restore:WaitlistEntry');
    }

    public function forceDelete(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('ForceDelete:WaitlistEntry');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WaitlistEntry');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WaitlistEntry');
    }

    public function replicate(AuthUser $authUser, WaitlistEntry $waitlistEntry): bool
    {
        return $authUser->can('Replicate:WaitlistEntry');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WaitlistEntry');
    }
}
