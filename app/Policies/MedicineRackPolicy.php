<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MedicineRack;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicineRackPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicineRack');
    }

    public function view(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('View:MedicineRack');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicineRack');
    }

    public function update(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('Update:MedicineRack');
    }

    public function delete(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('Delete:MedicineRack');
    }

    public function restore(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('Restore:MedicineRack');
    }

    public function forceDelete(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('ForceDelete:MedicineRack');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicineRack');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicineRack');
    }

    public function replicate(AuthUser $authUser, MedicineRack $medicineRack): bool
    {
        return $authUser->can('Replicate:MedicineRack');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicineRack');
    }

}