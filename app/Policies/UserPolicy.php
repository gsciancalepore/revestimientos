<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users.
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
     * Determine whether the user can create users.
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
     * Determine whether the user can update a user.
     */
    public function update(User $user, User $target): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }

    /**
     * Determine whether the user can deactivate or reactivate a user.
     */
    public function toggleActive(User $user, User $target): bool
    {
        try {
            return $user->role() === UserRole::Admin;
        } catch (\DomainException $e) {
            return false;
        }
    }
}
