<?php

namespace App\Http\Controllers;

use App\Actions\ImportShippingRatesAction;
use App\Http\Requests\ShippingRates\ConfirmShippingImportRequest;
use App\Http\Requests\ShippingRates\ImportShippingRatesRequest;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\ShippingRatesCsvParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ShippingRateImportController extends Controller
{
    /**
     * Minutos de vida del temporal y su manifiesto (spec fase 2, paso 2).
     */
    private const TTL_MINUTOS = 30;

    private const TMP_DIR = 'tmp/shipping-imports';

    public function __construct(
        private ShippingRatesCsvParser $parser,
        private ImportShippingRatesAction $importShippingRates,
    ) {}

    public function import(): View
    {
        Gate::authorize('import', ShippingRate::class);

        $this->purgeExpired();

        return view('admin.tarifas-envio.import');
    }

    /**
     * Paso 2: valida el CSV entero. Error de contenido → 422 sin persistir nada;
     * éxito → temporal + manifiesto y redirect al preview (PRG).
     */
    public function upload(ImportShippingRatesRequest $request): Response|RedirectResponse
    {
        Gate::authorize('import', ShippingRate::class);

        $file = $request->file('csv');

        if (! $file instanceof UploadedFile) {
            return $this->rejectUpload(['El archivo CSV es obligatorio.']);
        }

        $content = file_get_contents($file->getRealPath());

        if ($content === false) {
            return $this->rejectUpload(['No se pudo leer el archivo.']);
        }

        $parsed = $this->parser->parse($content);

        if ($parsed['errors'] !== []) {
            return $this->rejectUpload($parsed['errors']);
        }

        $token = Str::random(40);
        $path = self::TMP_DIR."/{$token}.csv";

        Storage::disk('local')->put($path, $content);

        Cache::put($this->cacheKey($token), [
            'user_id' => $this->authenticatedUser($request)->id,
            'path' => $path,
            'hash' => sha1($content),
            'conteos' => $this->conteos($parsed['rows']),
            'expires_at' => now()->addMinutes(self::TTL_MINUTOS)->toDateTimeString(),
        ], now()->addMinutes(self::TTL_MINUTOS));

        return redirect()->route('tarifas-envio.import.preview', ['token' => $token]);
    }

    /**
     * Paso 3: resumen del snapshot pendiente. Token ajeno, vencido o
     * inexistente → rechazo sin mutación.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        Gate::authorize('import', ShippingRate::class);

        $token = $request->query('token');

        if (! is_string($token)) {
            return $this->rejectToken();
        }

        $pending = $this->pendingFor($token, $request);

        if ($pending === null) {
            return $this->rejectToken();
        }

        $content = $this->verifiedContent($pending);

        if ($content === null) {
            $this->discard($token, $pending['path']);

            return $this->rejectTemp();
        }

        $parsed = $this->parser->parse($content);

        if ($parsed['errors'] !== []) {
            $this->discard($token, $pending['path']);

            return $this->rejectTemp();
        }

        return view('admin.tarifas-envio.preview', [
            'token' => $token,
            'total' => $pending['conteos']['total'],
            'nuevas' => $pending['conteos']['nuevas'],
            'aActualizar' => $pending['conteos']['aActualizar'],
            'sinCambios' => $pending['conteos']['sinCambios'],
            'aDesactivar' => $pending['conteos']['aDesactivar'],
            'muestra' => array_slice($parsed['rows'], 0, 20),
        ]);
    }

    /**
     * Paso 4: re-parsea y revalida desde el temporal, aplica en transacción y limpia.
     */
    public function confirm(ConfirmShippingImportRequest $request): RedirectResponse
    {
        Gate::authorize('import', ShippingRate::class);

        /** @var string $token */
        $token = $request->validated('token');

        $pending = $this->pendingFor($token, $request);

        if ($pending === null) {
            return $this->rejectToken();
        }

        $content = $this->verifiedContent($pending);

        if ($content === null) {
            $this->discard($token, $pending['path']);

            return $this->rejectTemp();
        }

        $parsed = $this->parser->parse($content);

        if ($parsed['errors'] !== []) {
            $this->discard($token, $pending['path']);

            return $this->rejectTemp();
        }

        $summary = $this->importShippingRates->execute(
            array_map(fn (array $row): array => [
                'cp' => $row['cp'],
                'costo_cents' => $row['costo_cents'],
            ], $parsed['rows'])
        );

        $this->discard($token, $pending['path']);

        return redirect()->route('tarifas-envio.index')->with(
            'status',
            "Importación completa: {$summary['created']} nuevas, {$summary['updated']} actualizadas, {$summary['unchanged']} sin cambios, {$summary['deactivated']} desactivadas."
        );
    }

    /**
     * Cancelar descarta el temporal y su token (ADR-011: limpieza al cancelar).
     */
    public function cancel(ConfirmShippingImportRequest $request): RedirectResponse
    {
        Gate::authorize('import', ShippingRate::class);

        /** @var string $token */
        $token = $request->validated('token');

        $pending = $this->pendingFor($token, $request);

        if ($pending !== null) {
            $this->discard($token, $pending['path']);
        }

        return redirect()->route('tarifas-envio.index')->with('status', 'Importación cancelada.');
    }

    /**
     * @param  list<array{line: int, cp: string, precio: int, costo_cents: int}>  $rows
     * @return array{total: int, nuevas: int, aActualizar: int, sinCambios: int, aDesactivar: int}
     */
    private function conteos(array $rows): array
    {
        /** @var list<string> $cps */
        $cps = array_map(fn (array $row): string => $row['cp'], $rows);

        $activas = ShippingRate::query()->whereIn('cp', $cps)->activo()->get()->keyBy('cp');

        $nuevas = 0;
        $aActualizar = 0;
        $sinCambios = 0;

        foreach ($rows as $row) {
            $rate = $activas->get($row['cp']);

            if (! $rate instanceof ShippingRate) {
                $nuevas++;

                continue;
            }

            if ($rate->costo_cents !== $row['costo_cents']) {
                $aActualizar++;

                continue;
            }

            $sinCambios++;
        }

        return [
            'total' => count($rows),
            'nuevas' => $nuevas,
            'aActualizar' => $aActualizar,
            'sinCambios' => $sinCambios,
            'aDesactivar' => ShippingRate::query()->activo()->whereNotIn('cp', $cps)->count(),
        ];
    }

    /**
     * Manifiesto vigente y propio del usuario, o null.
     *
     * @return array{user_id: int, path: string, hash: string, conteos: array{total: int, nuevas: int, aActualizar: int, sinCambios: int, aDesactivar: int}, expires_at: string}|null
     */
    private function pendingFor(string $token, Request $request): ?array
    {
        /** @var array{user_id: int, path: string, hash: string, conteos: array{total: int, nuevas: int, aActualizar: int, sinCambios: int, aDesactivar: int}, expires_at: string}|null $pending */
        $pending = Cache::get($this->cacheKey($token));

        if ($pending === null || $pending['user_id'] !== $this->authenticatedUser($request)->id) {
            return null;
        }

        return $pending;
    }

    /**
     * Contenido del temporal si existe y su hash coincide con el manifiesto.
     *
     * @param  array{user_id: int, path: string, hash: string, conteos: array{total: int, nuevas: int, aActualizar: int, sinCambios: int, aDesactivar: int}, expires_at: string}  $pending
     */
    private function verifiedContent(array $pending): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($pending['path'])) {
            return null;
        }

        $content = (string) $disk->get($pending['path']);

        return sha1($content) === $pending['hash'] ? $content : null;
    }

    /**
     * Borra los temporales vencidos que quedaron sin confirmar ni cancelar.
     */
    private function purgeExpired(): void
    {
        $disk = Storage::disk('local');
        $limite = now()->subMinutes(self::TTL_MINUTOS)->getTimestamp();

        foreach ($disk->files(self::TMP_DIR) as $path) {
            if ($disk->lastModified($path) < $limite) {
                $disk->delete($path);
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function rejectUpload(array $errors): Response
    {
        return response(
            view('admin.tarifas-envio.import')->withErrors(['csv' => $errors]),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function rejectToken(): RedirectResponse
    {
        return redirect()->route('tarifas-envio.import')->withErrors([
            'token' => 'La importación expiró o no es válida. Volvé a subir el archivo.',
        ]);
    }

    private function rejectTemp(): RedirectResponse
    {
        return redirect()->route('tarifas-envio.import')->withErrors([
            'csv' => 'El archivo temporal no es válido. Volvé a subir el archivo.',
        ]);
    }

    private function cacheKey(string $token): string
    {
        return "shipping-import:{$token}";
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function discard(string $token, string $path): void
    {
        Cache::forget($this->cacheKey($token));
        Storage::disk('local')->delete($path);
    }
}
