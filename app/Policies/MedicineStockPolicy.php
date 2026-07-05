<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MedicineStock;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicineStockPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicineStock');
    }

    public function view(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('View:MedicineStock');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicineStock');
    }

    public function update(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('Update:MedicineStock');
    }

    public function delete(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('Delete:MedicineStock');
    }

    public function restore(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('Restore:MedicineStock');
    }

    public function forceDelete(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('ForceDelete:MedicineStock');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicineStock');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicineStock');
    }

    public function replicate(AuthUser $authUser, MedicineStock $medicineStock): bool
    {
        return $authUser->can('Replicate:MedicineStock');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicineStock');
    }

}