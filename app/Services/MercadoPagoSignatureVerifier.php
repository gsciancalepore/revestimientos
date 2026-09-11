<?php

namespace App\Services;

use Illuminate\Http\Request;

class MercadoPagoSignatureVerifier
{
    /**
     * Valida el header `x-signature` de MercadoPago (Spec 08, regla 154).
     *
     * El manifiesto que se firma es `id:<data.id>;request-id:<x-request-id>;ts:<ts>;`
     * y la comparación es en tiempo constante. Sin secreto configurado nada se
     * considera válido: preferible responder 401 a todo antes que aceptar
     * notificaciones sin verificar.
     */
    public function verify(Request $request, string $paymentId): bool
    {
        $secreto = config('services.mercadopago.webhook_secret');

        if (! is_string($secreto) || $secreto === '') {
            return false;
        }

        $firma = $request->header('x-signature');
        $requestId = $request->header('x-request-id');

        if (! is_string($firma) || ! is_string($requestId)) {
            return false;
        }

        $partes = $this->parse($firma);

        if ($partes['ts'] === null || $partes['v1'] === null) {
            return false;
        }

        $esperado = hash_hmac(
            'sha256',
            "id:{$paymentId};request-id:{$requestId};ts:{$partes['ts']};",
            $secreto
        );

        return hash_equals($esperado, $partes['v1']);
    }

    /**
     * `ts=1700000000,v1=abc...` → sus dos componentes.
     *
     * @return array{ts: ?string, v1: ?string}
     */
    private function parse(string $firma): array
    {
        $partes = ['ts' => null, 'v1' => null];

        foreach (explode(',', $firma) as $fragmento) {
            $par = explode('=', trim($fragmento), 2);

            if (count($par) !== 2) {
                continue;
            }

            [$clave, $valor] = $par;

            if (array_key_exists(trim($clave), $partes)) {
                $partes[trim($clave)] = trim($valor);
            }
        }

        return $partes;
    }
}
