<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PricingRule;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PricingRulePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PricingRule');
    }

    public function view(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('View:PricingRule');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PricingRule');
    }

    public function update(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('Update:PricingRule');
    }

    public function delete(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('Delete:PricingRule');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PricingRule');
    }

    public function restore(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('Restore:PricingRule');
    }

    public function forceDelete(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('ForceDelete:PricingRule');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PricingRule');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PricingRule');
    }

    public function replicate(AuthUser $authUser, PricingRule $pricingRule): bool
    {
        return $authUser->can('Replicate:PricingRule');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PricingRule');
    }
}
