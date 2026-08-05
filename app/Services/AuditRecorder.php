<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    /**
     * Record an audited action (ADR-004, Spec 01 rule 42).
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function record(string $action, ?Model $subject = null, ?array $payload = null): void
    {
        AuditLog::create([
            'actor_type' => auth()->user()?->getMorphClass(),
            'actor_id' => auth()->user()?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
