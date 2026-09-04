<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view the list of products.
     */
    public function viewAny(User $user): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Determine whether the user can update a product.
     */
    public function update(User $user, Product $product): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete a product.
     */
    public function delete(User $user, Product $product): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }
}
