<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditRecorder;

class UpdateUserAction
{
    public function __construct(private AuditRecorder $recorder)
    {
    }

    public function execute(User $user, string $name, string $email, ?UserRole $role = null, ?string $password = null): User
    {
        $user->fill([
            'name' => $name,
            'email' => $email,
        ]);

        if ($password !== null) {
            $user->password = $password;
        }

        $changes = $user->getDirty();

        $user->save();

        if ($changes !== []) {
            $this->recorder->record('user.updated', $user, [
                'changes' => array_keys($changes),
            ]);
        }

        if ($role !== null && $role !== $user->role()) {
            $previousRole = $user->role();
            $user->syncRoles([$role->value]);

            $this->recorder->record('user.role_changed', $user, [
                'previous_role' => $previousRole->value,
                'new_role' => $role->value,
            ]);
        }

        return $user;
    }
}
