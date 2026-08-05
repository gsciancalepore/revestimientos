<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditRecorder;

class CreateUserAction
{
    public function __construct(private AuditRecorder $recorder)
    {
    }

    public function execute(string $name, string $email, string $password, UserRole $role): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->assignRole($role->value);

        $this->recorder->record('user.created', $user, [
            'role' => $role->value,
        ]);

        return $user;
    }
}
