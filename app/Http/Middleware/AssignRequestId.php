<?php

namespace App\Http\Middleware;

use App\Logging\EventLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlación y resumen por request (OBS-03, OBS-04).
 *
 * El `request_id` se genera siempre acá: un `X-Request-Id` entrante no se
 * adopta nunca, porque lo manda cualquiera (MercadoPago incluido). Vive en el
 * `Context`, que es `scoped` y bajo Octane se vacía entre requests.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('obs_inicio', hrtime(true));
        Context::add('request_id', (string) Str::uuid());

        $response = $next($request);
        $response->headers->set('X-Request-Id', (string) Context::get('request_id'));

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $inicio = $request->attributes->get('obs_inicio');

        $attributes = [
            'method' => $request->getMethod(),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => is_int($inicio) ? intdiv(hrtime(true) - $inicio, 1_000_000) : 0,
        ];

        if ($request->user() !== null) {
            $attributes['user_id'] = $request->user()->getKey();
        }

        EventLog::record('http.request', $attributes);
    }
}
