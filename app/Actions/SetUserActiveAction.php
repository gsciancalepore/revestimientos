<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AuditRecorder;
use DomainException;

class SetUserActiveAction
{
    public function __construct(private AuditRecorder $recorder)
    {
    }

    public function execute(User $user, bool $active, ?User $actor = null): User
    {
        if ($actor !== null && $actor->is($user) && ! $active) {
            throw new DomainException('El admin no puede desactivarse a sí mismo (Spec 01).');
        }

        $user->update(['is_active' => $active]);

        $this->recorder->record($active ? 'user.reactivated' : 'user.deactivated', $user);

        return $user;
    }
}
