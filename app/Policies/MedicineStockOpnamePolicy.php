<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MedicineStockOpname;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicineStockOpnamePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicineStockOpname');
    }

    public function view(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('View:MedicineStockOpname');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicineStockOpname');
    }

    public function update(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('Update:MedicineStockOpname');
    }

    public function delete(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('Delete:MedicineStockOpname');
    }

    public function restore(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('Restore:MedicineStockOpname');
    }

    public function forceDelete(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('ForceDelete:MedicineStockOpname');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicineStockOpname');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicineStockOpname');
    }

    public function replicate(AuthUser $authUser, MedicineStockOpname $medicineStockOpname): bool
    {
        return $authUser->can('Replicate:MedicineStockOpname');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicineStockOpname');
    }

}