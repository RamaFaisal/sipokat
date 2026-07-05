<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SawCalculation;
use Illuminate\Auth\Access\HandlesAuthorization;

class SawCalculationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SawCalculation');
    }

    public function view(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('View:SawCalculation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SawCalculation');
    }

    public function update(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('Update:SawCalculation');
    }

    public function delete(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('Delete:SawCalculation');
    }

    public function restore(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('Restore:SawCalculation');
    }

    public function forceDelete(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('ForceDelete:SawCalculation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SawCalculation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SawCalculation');
    }

    public function replicate(AuthUser $authUser, SawCalculation $sawCalculation): bool
    {
        return $authUser->can('Replicate:SawCalculation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SawCalculation');
    }

}