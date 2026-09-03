<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShippingRate;
use App\Models\User;

class ShippingRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role() === UserRole::Admin;
    }

    public function view(User $user, ShippingRate $shippingRate): bool
    {
        return $user->role() === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role() === UserRole::Admin;
    }

    public function update(User $user, ShippingRate $shippingRate): bool
    {
        return $user->role() === UserRole::Admin;
    }

    public function delete(User $user, ShippingRate $shippingRate): bool
    {
        return $user->role() === UserRole::Admin;
    }
}
