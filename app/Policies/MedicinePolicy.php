<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Medicine;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicinePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Medicine');
    }

    public function view(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('View:Medicine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Medicine');
    }

    public function update(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('Update:Medicine');
    }

    public function delete(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('Delete:Medicine');
    }

    public function restore(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('Restore:Medicine');
    }

    public function forceDelete(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('ForceDelete:Medicine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Medicine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Medicine');
    }

    public function replicate(AuthUser $authUser, Medicine $medicine): bool
    {
        return $authUser->can('Replicate:Medicine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Medicine');
    }

}