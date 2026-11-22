<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WalletPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Wallet');
    }

    public function view(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('View:Wallet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Wallet');
    }

    public function update(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Update:Wallet');
    }

    public function delete(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Delete:Wallet');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Wallet');
    }

    public function restore(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Restore:Wallet');
    }

    public function forceDelete(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('ForceDelete:Wallet');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Wallet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Wallet');
    }

    public function replicate(AuthUser $authUser, Wallet $wallet): bool
    {
        return $authUser->can('Replicate:Wallet');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Wallet');
    }
}
