<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MedicineCategories;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicineCategoriesPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicineCategories');
    }

    public function view(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('View:MedicineCategories');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicineCategories');
    }

    public function update(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('Update:MedicineCategories');
    }

    public function delete(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('Delete:MedicineCategories');
    }

    public function restore(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('Restore:MedicineCategories');
    }

    public function forceDelete(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('ForceDelete:MedicineCategories');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicineCategories');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicineCategories');
    }

    public function replicate(AuthUser $authUser, MedicineCategories $medicineCategories): bool
    {
        return $authUser->can('Replicate:MedicineCategories');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicineCategories');
    }

}