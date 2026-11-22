<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WithdrawCharge;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WithdrawChargePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WithdrawCharge');
    }

    public function view(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('View:WithdrawCharge');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WithdrawCharge');
    }

    public function update(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('Update:WithdrawCharge');
    }

    public function delete(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('Delete:WithdrawCharge');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WithdrawCharge');
    }

    public function restore(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('Restore:WithdrawCharge');
    }

    public function forceDelete(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('ForceDelete:WithdrawCharge');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WithdrawCharge');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WithdrawCharge');
    }

    public function replicate(AuthUser $authUser, WithdrawCharge $withdrawCharge): bool
    {
        return $authUser->can('Replicate:WithdrawCharge');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WithdrawCharge');
    }
}
