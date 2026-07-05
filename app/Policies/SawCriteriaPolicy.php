<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SawCriteria;
use Illuminate\Auth\Access\HandlesAuthorization;

class SawCriteriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SawCriteria');
    }

    public function view(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('View:SawCriteria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SawCriteria');
    }

    public function update(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('Update:SawCriteria');
    }

    public function delete(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('Delete:SawCriteria');
    }

    public function restore(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('Restore:SawCriteria');
    }

    public function forceDelete(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('ForceDelete:SawCriteria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SawCriteria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SawCriteria');
    }

    public function replicate(AuthUser $authUser, SawCriteria $sawCriteria): bool
    {
        return $authUser->can('Replicate:SawCriteria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SawCriteria');
    }

}