<?php

use App\Enums\UserRole;
use App\Models\ShippingRate;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Storage::fake('local');
});

function importAdminUser(): User
{
    return User::factory()->withRole(UserRole::Admin)->create();
}

function importVendedorUser(): User
{
    return User::factory()->withRole(UserRole::Vendedor)->create();
}

function importCsvFile(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('tarifas.csv', $body);
}

/**
 * Paso 2: sube el CSV. Devuelve 422 (error de contenido) o redirect al preview.
 */
function importUpload(mixed $test, string $body, ?User $user = null): TestResponse
{
    $user ??= importAdminUser();

    return $test->actingAs($user)->post(route('tarifas-envio.import.upload'), [
        'csv' => importCsvFile($body),
    ]);
}

/**
 * Extrae el token del redirect al preview (patrón PRG del paso 2 → paso 3).
 */
function importTokenFromRedirect(TestResponse $response): string
{
    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['token'] ?? null)->toBeString();

    /** @var string $token */
    $token = $query['token'];

    return $token;
}

/**
 * Sube el CSV y devuelve el token del temporal pendiente.
 */
function importPendingToken(mixed $test, string $body, ?User $user = null): string
{
    return importTokenFromRedirect(importUpload($test, $body, $user));
}

/**
 * Contrato del paso 2 ante error de contenido: 422 + formulario con los errores.
 */
function assertRejectedAsUnprocessable(TestResponse $response): void
{
    $response->assertStatus(422)->assertViewIs('admin.tarifas-envio.import');

    $errors = $response->original->getData()['errors'];

    expect($errors->has('csv'))->toBeTrue();
}

function importTempFiles(): array
{
    return Storage::disk('local')->files('tmp/shipping-imports');
}

test('invitado es redirigido al login al abrir importar', function () {
    $this->get(route('tarifas-envio.import'))->assertRedirect(route('login'));
});

test('vendedor recibe 403 en importar, preview, confirmar y cancelar', function () {
    $vendedor = importVendedorUser();

    $this->actingAs($vendedor)->get(route('tarifas-envio.import'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('tarifas-envio.import.upload'), [
        'csv' => importCsvFile("codigo_postal,precio_envio\n1000,10000\n"),
    ])->assertForbidden();
    $this->actingAs($vendedor)->get(route('tarifas-envio.import.preview', ['token' => str_repeat('a', 40)]))->assertForbidden();
    $this->actingAs($vendedor)->post(route('tarifas-envio.import.confirm'), [
        'token' => str_repeat('a', 40),
    ])->assertForbidden();
    $this->actingAs($vendedor)->post(route('tarifas-envio.import.cancel'), [
        'token' => str_repeat('a', 40),
    ])->assertForbidden();
});

test('admin ve el formulario de importación', function () {
    $this->actingAs(importAdminUser())->get(route('tarifas-envio.import'))->assertOk();
});

test('upload válido redirige al preview con el token', function () {
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n");

    expect($token)->toHaveLength(40);
    expect(importTempFiles())->toHaveCount(1);
});

test('preview muestra los conteos del snapshot pendiente', function () {
    ShippingRate::factory()->create(['cp' => '1000', 'costo_cents' => 1000000, 'activo' => true]);
    ShippingRate::factory()->create(['cp' => '3000', 'costo_cents' => 500000, 'activo' => true]);
    $admin = importAdminUser();

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n1001,18000\n", $admin);

    $response = $this->actingAs($admin)->get(route('tarifas-envio.import.preview', ['token' => $token]));

    $response->assertOk()->assertViewIs('admin.tarifas-envio.preview');
    $response->assertViewHasAll([
        'total' => 2,
        'nuevas' => 1,
        'aActualizar' => 0,
        'sinCambios' => 1,
        'aDesactivar' => 1,
    ]);
});

test('cabecera inválida responde 422 y nada persiste', function () {
    assertRejectedAsUnprocessable(importUpload($this, "cp,precio\n1000,10000\n"));

    expect(ShippingRate::query()->count())->toBe(0);
    expect(importTempFiles())->toBeEmpty();
});

test('archivo vacío o sin filas responde 422', function () {
    assertRejectedAsUnprocessable(importUpload($this, ''));
    assertRejectedAsUnprocessable(importUpload($this, "codigo_postal,precio_envio\n"));

    expect(ShippingRate::query()->count())->toBe(0);
});

test('archivo que no es csv responde 422 desde el form request', function () {
    $response = $this->actingAs(importAdminUser())->post(route('tarifas-envio.import.upload'), [
        'csv' => UploadedFile::fake()->create('tarifas.pdf', 10, 'application/pdf'),
    ]);

    assertRejectedAsUnprocessable($response);
});

test('upload sin archivo responde 422 desde el form request', function () {
    assertRejectedAsUnprocessable(
        $this->actingAs(importAdminUser())->post(route('tarifas-envio.import.upload'), [])
    );
});

test('cp con formato inválido responde 422 con la línea y nada persiste', function () {
    foreach (['ABC', '123', '12345', '0123A', ''] as $cp) {
        assertRejectedAsUnprocessable(
            importUpload($this, "codigo_postal,precio_envio\n1000,10000\n{$cp},10000\n")
        );
    }

    expect(ShippingRate::query()->count())->toBe(0);
});

test('el detalle del error indica el número de línea', function () {
    $response = importUpload($this, "codigo_postal,precio_envio\n1000,10000\nABC,10000\n");

    $response->assertStatus(422);
    $errors = $response->original->getData()['errors'];

    expect($errors->get('csv'))->toContain('Línea 3: el código postal debe tener 4 dígitos.');
});

test('cp duplicado dentro del archivo responde 422 y nada persiste', function () {
    assertRejectedAsUnprocessable(
        importUpload($this, "codigo_postal,precio_envio\n1000,10000\n1000,18000\n")
    );

    expect(ShippingRate::query()->count())->toBe(0);
});

test('precio negativo, no entero o mayor al máximo responde 422', function () {
    foreach (['-100', '10.5', '10,000', 'abc', '', '92233720368547759'] as $precio) {
        assertRejectedAsUnprocessable(
            importUpload($this, "codigo_postal,precio_envio\n1000,{$precio}\n")
        );
    }

    expect(ShippingRate::query()->count())->toBe(0);
});

test('archivo con una fila válida y una inválida no persiste nada', function () {
    assertRejectedAsUnprocessable(
        importUpload($this, "codigo_postal,precio_envio\n1000,10000\nABC,10000\n")
    );

    expect(ShippingRate::query()->count())->toBe(0);
    expect(importTempFiles())->toBeEmpty();
});

test('cp con ceros iniciales se conserva como string', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n0123,10000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    $rate = ShippingRate::query()->where('cp', '0123')->first();
    expect($rate)->not->toBeNull();
    expect($rate->costo_cents)->toBe(1000000);
});

test('precio 0 es aceptado', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,0\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1000')->first()->costo_cents)->toBe(0);
});

test('precio en pesos se convierte a centavos multiplicando por 100', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n1001,18000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1000')->first()->costo_cents)->toBe(1000000);
    expect(ShippingRate::query()->where('cp', '1001')->first()->costo_cents)->toBe(1800000);
});

test('confirmar crea nuevas, actualiza distintas y no toca iguales', function () {
    $admin = importAdminUser();
    $igual = ShippingRate::factory()->create(['cp' => '1000', 'costo_cents' => 1000000, 'activo' => true]);
    $distinta = ShippingRate::factory()->create(['cp' => '1001', 'costo_cents' => 500000, 'activo' => true]);
    $igualUpdatedAt = $igual->updated_at->toDateTimeString();

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n1001,18000\n1002,10000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1002')->first()->costo_cents)->toBe(1000000);
    expect($distinta->fresh()->costo_cents)->toBe(1800000);
    expect($igual->fresh()->updated_at->toDateTimeString())->toBe($igualUpdatedAt);
});

test('activa ausente del snapshot se desactiva; histórica inactiva no se toca; sin deletes', function () {
    $admin = importAdminUser();
    $ausente = ShippingRate::factory()->create(['cp' => '1000', 'costo_cents' => 1000000, 'activo' => true]);
    $historica = ShippingRate::factory()->inactive()->create(['cp' => '2000', 'costo_cents' => 999, 'activo' => false]);
    ShippingRate::factory()->create(['cp' => '1001', 'costo_cents' => 1000000, 'activo' => true]);

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1001,10000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect($ausente->fresh()->activo)->toBeFalse();
    expect($historica->fresh()->toArray())->toMatchArray(['cp' => '2000', 'costo_cents' => 999, 'activo' => false]);
    expect(ShippingRate::query()->count())->toBe(3);
});

test('cp solo con historial inactivo y presente crea nueva activa sin reactivar', function () {
    $admin = importAdminUser();
    $historica = ShippingRate::factory()->inactive()->create(['cp' => '1000', 'costo_cents' => 111, 'activo' => false]);

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->where('cp', '1000')->count())->toBe(2);
    expect($historica->fresh()->toArray())->toMatchArray(['costo_cents' => 111, 'activo' => false]);

    $nueva = ShippingRate::query()->where('cp', '1000')->activo()->first();
    expect($nueva->id)->not->toBe($historica->id);
    expect($nueva->costo_cents)->toBe(1000000);
});

test('reimportar el mismo csv es idempotente', function () {
    $admin = importAdminUser();
    $csv = "codigo_postal,precio_envio\n1000,10000\n1001,18000\n";

    $token = importPendingToken($this, $csv, $admin);
    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), ['token' => $token]);

    $fingerprintAntes = ShippingRate::query()->orderBy('id')->get()->map(
        fn (ShippingRate $r): string => "{$r->id}:{$r->cp}:{$r->costo_cents}:".($r->activo ? '1' : '0').":{$r->updated_at}"
    )->all();

    $token2 = importPendingToken($this, $csv, $admin);
    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), ['token' => $token2]);

    $fingerprintDespues = ShippingRate::query()->orderBy('id')->get()->map(
        fn (ShippingRate $r): string => "{$r->id}:{$r->cp}:{$r->costo_cents}:".($r->activo ? '1' : '0').":{$r->updated_at}"
    )->all();

    expect($fingerprintDespues)->toBe($fingerprintAntes);
});

test('archivo manipulado entre preview y confirm no muta nada y descarta el temporal', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    foreach (importTempFiles() as $path) {
        Storage::disk('local')->put($path, "codigo_postal,precio_envio\nABC,10000\n");
    }

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.import'));

    expect(ShippingRate::query()->count())->toBe(0);
    expect(importTempFiles())->toBeEmpty();
});

test('token inexistente, de otro usuario o reutilizado no muta nada', function () {
    $admin = importAdminUser();
    $otroAdmin = User::factory()->withRole(UserRole::Admin)->create();

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => str_repeat('b', 40),
    ])->assertRedirect(route('tarifas-envio.import'));

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->actingAs($otroAdmin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.import'));

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.import'));

    expect(ShippingRate::query()->where('cp', '1000')->count())->toBe(1);
});

test('preview con token ajeno, inexistente o ausente rechaza sin mutación', function () {
    $admin = importAdminUser();
    $otroAdmin = User::factory()->withRole(UserRole::Admin)->create();

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->actingAs($otroAdmin)->get(route('tarifas-envio.import.preview', ['token' => $token]))
        ->assertRedirect(route('tarifas-envio.import'));

    $this->actingAs($admin)->get(route('tarifas-envio.import.preview', ['token' => str_repeat('c', 40)]))
        ->assertRedirect(route('tarifas-envio.import'));

    $this->actingAs($admin)->get(route('tarifas-envio.import.preview'))
        ->assertRedirect(route('tarifas-envio.import'));

    expect(ShippingRate::query()->count())->toBe(0);
});

test('token vencido rechaza el preview y la confirmación sin mutación', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->travel(31)->minutes();

    $this->actingAs($admin)->get(route('tarifas-envio.import.preview', ['token' => $token]))
        ->assertRedirect(route('tarifas-envio.import'));

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.import'));

    expect(ShippingRate::query()->count())->toBe(0);
});

test('cancelar descarta el temporal y su token sin mutación', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    expect(importTempFiles())->toHaveCount(1);

    $this->actingAs($admin)->post(route('tarifas-envio.import.cancel'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(importTempFiles())->toBeEmpty();
    expect(ShippingRate::query()->count())->toBe(0);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.import'));

    expect(ShippingRate::query()->count())->toBe(0);
});

test('confirmar deja el temporal limpio', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), ['token' => $token]);

    expect(importTempFiles())->toBeEmpty();
});

test('abrir el importador purga los temporales vencidos y respeta los vigentes', function () {
    $admin = importAdminUser();
    importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    expect(importTempFiles())->toHaveCount(1);

    $this->travel(31)->minutes();

    $this->actingAs($admin)->get(route('tarifas-envio.import'))->assertOk();

    expect(importTempFiles())->toBeEmpty();
});

test('abrir el importador no purga un temporal vigente', function () {
    $admin = importAdminUser();
    importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n", $admin);

    $this->travel(5)->minutes();

    $this->actingAs($admin)->get(route('tarifas-envio.import'))->assertOk();

    expect(importTempFiles())->toHaveCount(1);
});

test('un fallo durante la confirmación revierte toda la importación', function () {
    $admin = importAdminUser();
    $aActualizar = ShippingRate::factory()->create(['cp' => '1000', 'costo_cents' => 500000, 'activo' => true]);
    $ausenteDelSnapshot = ShippingRate::factory()->create(['cp' => '9999', 'costo_cents' => 700000, 'activo' => true]);

    $token = importPendingToken($this, "codigo_postal,precio_envio\n1000,10000\n1001,18000\n", $admin);

    // Falla al crear la segunda tarifa, con el UPDATE de la primera ya aplicado
    // dentro de la transacción (regla 138).
    ShippingRate::creating(function (ShippingRate $rate): void {
        if ($rate->cp === '1001') {
            throw new RuntimeException('fallo simulado durante la importación');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), ['token' => $token]))
        ->toThrow(RuntimeException::class);

    // Rollback total: el UPDATE previo al fallo se revierte y no queda nada creado.
    expect($aActualizar->fresh()->costo_cents)->toBe(500000);
    expect(ShippingRate::query()->where('cp', '1001')->exists())->toBeFalse();
    expect($ausenteDelSnapshot->fresh()->activo)->toBeTrue();
    expect(ShippingRate::query()->count())->toBe(2);
});

test('bom y finales de línea crlf se aceptan', function () {
    $admin = importAdminUser();
    $token = importPendingToken($this, "\xEF\xBB\xBFcodigo_postal,precio_envio\r\n1000,10000\r\n1001,18000\r\n", $admin);

    $this->actingAs($admin)->post(route('tarifas-envio.import.confirm'), [
        'token' => $token,
    ])->assertRedirect(route('tarifas-envio.index'));

    expect(ShippingRate::query()->count())->toBe(2);
    expect(ShippingRate::query()->where('cp', '1000')->first()->costo_cents)->toBe(1000000);
});
