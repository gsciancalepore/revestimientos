<?php

namespace App\Actions;

use App\Contracts\PaymentStatusQuery;
use App\Logging\EventLog;
use App\Models\Order;
use App\Services\AuditRecorder;

class ProcessMercadoPagoNotificationAction
{
    public function __construct(
        private PaymentStatusQuery $pagos,
        private ConfirmPaymentAction $confirm,
        private AuditRecorder $recorder,
    ) {}

    /**
     * Procesa una notificación de pago ya autenticada (Spec 08, reglas 155 a 158).
     *
     * Del webhook solo llega el ID: el estado real se consulta contra la API y la
     * decisión se toma con esa respuesta, nunca con lo que llegó por HTTP. Un
     * fallo transitorio de la consulta se propaga para que el controlador
     * responda no-200 y MercadoPago reintente (regla 153).
     */
    public function execute(string $paymentId): void
    {
        $pago = $this->pagos->findPayment($paymentId);

        if ($pago === null) {
            $this->recorder->record('webhook.payment_not_found', null, ['payment_id' => $paymentId]);

            return;
        }

        // Regla 158: solo `approved` confirma. `pending`/`in_process` todavía no
        // acreditaron y `rejected`/`cancelled` dejan el pedido reintentable.
        if ($pago['status'] !== 'approved') {
            EventLog::record('webhook.payment_not_approved', [
                'payment_id' => $paymentId,
                'status' => $pago['status'],
                'order_id' => $this->pedidoReferido($pago['external_reference']),
            ]);

            return;
        }

        $order = $this->localizarPedido($pago['external_reference']);

        // Regla 156: puede ser una notificación de otra aplicación o una prueba.
        if ($order === null) {
            $this->recorder->record('webhook.order_not_found', null, [
                'payment_id' => $paymentId,
                'external_reference' => $pago['external_reference'],
            ]);

            return;
        }

        // Regla 157: un pago por un monto distinto al total no confirma el pedido.
        // Nace de un defecto real: la preferencia omitía el envío y MercadoPago
        // cobraba el subtotal.
        if ($pago['amount_cents'] !== $order->total_cents) {
            $this->recorder->record('order.payment_amount_mismatch', $order, [
                'payment_id' => $paymentId,
                'cobrado_cents' => $pago['amount_cents'],
                'total_cents' => $order->total_cents,
            ]);

            return;
        }

        $this->confirm->execute($order, 'mercadopago', $paymentId);
    }

    /**
     * El `order_id` que dice el `external_reference`, sin verificar que exista:
     * alcanza para correlacionar un pago no aprobado (OBS-05.5).
     */
    private function pedidoReferido(?string $externalReference): ?int
    {
        return $externalReference !== null && ctype_digit($externalReference) ? (int) $externalReference : null;
    }

    /**
     * El pedido se localiza por `external_reference`, que la regla 123 llenó con
     * el `order->id`.
     */
    private function localizarPedido(?string $externalReference): ?Order
    {
        if ($externalReference === null || ! ctype_digit($externalReference)) {
            return null;
        }

        /** @var Order|null $order */
        $order = Order::query()->find((int) $externalReference);

        return $order;
    }
}
