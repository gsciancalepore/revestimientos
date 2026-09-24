<?php

namespace App\Services;

use App\Logging\EventLog;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        $this->espejar($action, $subject, $payload ?? []);
    }

    /**
     * Espejo en el contrato de logs (spec observabilidad-01, OBS-05.1). Se
     * escribe después del commit: si la transacción se revierte, tampoco hay
     * fila de auditoría, y el log no puede contar algo que no pasó.
     *
     * @param  array<string, mixed>  $payload
     */
    private function espejar(string $action, ?Model $subject, array $payload): void
    {
        $attributes = [
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ];

        if ($subject instanceof Order) {
            $attributes['order_id'] = $subject->getKey();
        }

        // La fila de auditoría conserva `request_id`; en el log es el de
        // MercadoPago y no puede confundirse con el propio (OBS-05.1).
        if ($action === 'webhook.signature_invalid' && array_key_exists('request_id', $payload)) {
            $payload['mp_request_id'] = $payload['request_id'];
            unset($payload['request_id']);
        }

        $attributes = [...$attributes, ...$payload];

        DB::afterCommit(fn () => EventLog::record($action, $attributes, EventLog::nivelDeAuditoria($action)));
    }
}
