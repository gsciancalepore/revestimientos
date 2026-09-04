<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view the list of categories.
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
     * Determine whether the user can create categories.
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
     * Determine whether the user can update a category.
     */
    public function update(User $user, Category $category): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Determine whether the user can delete a category.
     */
    public function delete(User $user, Category $category): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }
}
