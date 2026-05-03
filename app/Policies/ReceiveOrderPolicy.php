<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReceiveOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiveOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReceiveOrder');
    }

    public function view(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('View:ReceiveOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReceiveOrder');
    }

    public function update(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('Update:ReceiveOrder');
    }

    public function delete(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('Delete:ReceiveOrder');
    }

    public function restore(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('Restore:ReceiveOrder');
    }

    public function forceDelete(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('ForceDelete:ReceiveOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReceiveOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReceiveOrder');
    }

    public function replicate(AuthUser $authUser, ReceiveOrder $receiveOrder): bool
    {
        return $authUser->can('Replicate:ReceiveOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReceiveOrder');
    }

}