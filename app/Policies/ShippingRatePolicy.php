<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ShippingRate;
use App\Models\User;

class ShippingRatePolicy
{
    public function viewAny(User $user): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    public function view(User $user, ShippingRate $shippingRate): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    public function create(User $user): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    public function update(User $user, ShippingRate $shippingRate): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    public function delete(User $user, ShippingRate $shippingRate): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }
}
