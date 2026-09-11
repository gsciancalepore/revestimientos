<?php

namespace App\Http\Controllers;

use App\Actions\ProcessMercadoPagoNotificationAction;
use App\Services\AuditRecorder;
use App\Services\MercadoPagoSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class MercadoPagoWebhookController extends Controller
{
    /**
     * `POST /webhook/mercadopago` (Spec 08, reglas 153 a 155).
     *
     * Responde 200 cuando la notificación fue procesada o cuando no había nada
     * que hacer, porque un 4xx/5xx hace que MercadoPago reintente. La excepción
     * es deliberada: si la consulta a la API falla por causa transitoria se
     * responde no-200 a propósito, para no perder el pago para siempre.
     */
    public function __invoke(
        Request $request,
        MercadoPagoSignatureVerifier $verifier,
        ProcessMercadoPagoNotificationAction $action,
        AuditRecorder $recorder,
    ): Response {
        $paymentId = $this->paymentId($request);

        if (! $verifier->verify($request, $paymentId)) {
            $recorder->record('webhook.signature_invalid', null, [
                'payment_id' => $paymentId,
                'request_id' => $request->header('x-request-id'),
            ]);

            return response('', 401);
        }

        // Filtro por tipo antes de consultar: MercadoPago manda varios tipos por
        // el mismo endpoint y un `merchant_order` con firma válida haría fallar
        // la consulta, porque su id no es el de un pago.
        if ($this->tipo($request) !== 'payment') {
            $recorder->record('webhook.ignored', null, [
                'tipo' => $this->tipo($request),
                'payment_id' => $paymentId,
            ]);

            return response('', 200);
        }

        try {
            $action->execute($paymentId);
        } catch (Throwable $e) {
            Log::error('mp webhook failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);

            return response('', 503);
        }

        return response('', 200);
    }

    /**
     * El ID del pago llega en el cuerpo (`data.id`). Como fallback se lee de la
     * query, donde PHP convierte el punto de `data.id` en guion bajo.
     */
    private function paymentId(Request $request): string
    {
        foreach ([$request->input('data.id'), $request->query('data_id'), $request->query('id')] as $valor) {
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }

            if (is_int($valor)) {
                return (string) $valor;
            }
        }

        return '';
    }

    /**
     * `type` es la notificación moderna; `topic`, el IPN viejo.
     */
    private function tipo(Request $request): string
    {
        foreach ([$request->input('type'), $request->input('topic'), $request->query('type'), $request->query('topic')] as $valor) {
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return '';
    }
}
