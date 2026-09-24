<?php

use App\Http\Middleware\AssignRequestId;
use App\Logging\EventLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Primero de todos, para que el `request_id` exista antes que cualquier
        // otra línea del request y la duración cubra el pipeline entero (OBS-03).
        $middleware->prepend(AssignRequestId::class);

        // El webhook de MercadoPago no tiene sesión de donde sacar un token:
        // lo autentica la firma `x-signature` (Spec 08, reglas 153 y 154).
        $middleware->validateCsrfTokens(except: ['webhook/mercadopago']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // OBS-07: `app.exception` reemplaza al reporte por defecto, que escribía
        // el mensaje crudo (SQL con valores incluido) en todos los canales.
        $exceptions->report(function (Throwable $e): void {
            EventLog::record('app.exception', [], 'error', $e);
        })->stop();
    })->create();
