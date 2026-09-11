<?php

namespace App\Contracts;

interface PaymentStatusQuery
{
    /**
     * Consulta el estado real de un pago contra el proveedor (Spec 08, regla 155).
     *
     * El contenido de la notificación nunca se cree: de ella se toma solo el ID
     * y la decisión se toma con lo que devuelve esta consulta. Vive en su propia
     * interfaz —y no en `PaymentGateway`— porque la transferencia bancaria no
     * tiene pago remoto que consultar (ADR-006: un puerto por capacidad).
     *
     * Devuelve `null` si el proveedor no conoce el pago. Un fallo transitorio
     * (timeout, 5xx) se propaga como excepción: "no hay nada que hacer" y "no
     * pude averiguar si había algo que hacer" son casos distintos (regla 153).
     *
     * @return array{status: string, external_reference: ?string, amount_cents: int}|null
     */
    public function findPayment(string $paymentId): ?array;
}
