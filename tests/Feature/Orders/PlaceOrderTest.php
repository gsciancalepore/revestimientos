<?php

use App\Actions\PlaceOrderAction;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Services\Cart;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Session::flush();
});

function cartWithProduct(Product $product, int $cantidad): Cart
{
    $cart = app(Cart::class);
    $cart->putItems([$product->id => $cantidad]);

    return $cart;
}

test('carrito vacio lanza DomainException y no crea pedido', function () {
    $cart = app(Cart::class);
    expect($cart->isEmpty())->toBeTrue();

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute('Juan', 'juan@test.com', '1122334455', '1407', 'Calle 123', 'transferencia'))
        ->toThrow(DomainException::class, 'El carrito está vacío.');

    expect(Order::count())->toBe(0);
});

test('carrito con linea no comprable (prevalidacion) lanza DomainException', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 2]);
    cartWithProduct($product, 2);

    // volver producto no comprable
    $product->update(['activo' => false]);

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute('Juan', 'juan@test.com', '1122334455', '1407', null, 'transferencia'))
        ->toThrow(DomainException::class);

    expect(Order::count())->toBe(0);
    // carrito intacto
    expect(app(Cart::class)->items())->toBe([$product->id => 2]);
});

// HIG-07: la revalidación bajo `lockForUpdate` (regla 109) no tenía cobertura.
// La prevalidación `hasUnpurchasable()` intercepta cualquier escenario armado
// desde el carrito, así que hay que simular la carrera real: el carrito leyó el
// stock antes de que otro pedido lo consumiera y su prevalidación quedó vieja.
// Este doble reproduce esa ventana; sin él no se llega nunca al lock.
function cartConPrevalidacionVieja(Product $product, int $cantidad): Cart
{
    $cart = new class extends Cart
    {
        public function hasUnpurchasable(): bool
        {
            return false;
        }
    };

    $cart->putItems([$product->id => $cantidad]);
    app()->instance(Cart::class, $cart);

    return $cart;
}

test('stock agotado despues de la prevalidacion lanza DomainException bajo lock', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartConPrevalidacionVieja($product, 3);

    // otro pedido consumió el stock entre la prevalidación y la transacción
    $product->update(['stock' => 1]);

    expect(fn () => app(PlaceOrderAction::class)->execute('Ana', 'ana@test.com', '1122334455', '1407', null, 'transferencia'))
        ->toThrow(DomainException::class, 'La cantidad solicitada supera el stock disponible.');

    expect(Order::count())->toBe(0);
    expect(app(Cart::class)->items())->toBe([$product->id => 3]);
});

test('producto desactivado despues de la prevalidacion lanza DomainException bajo lock', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartConPrevalidacionVieja($product, 2);

    // el admin desactivó el producto entre la prevalidación y la transacción
    $product->update(['activo' => false]);

    expect(fn () => app(PlaceOrderAction::class)->execute('Ana', 'ana@test.com', '1122334455', '1407', null, 'transferencia'))
        ->toThrow(DomainException::class, 'El producto no está disponible.');

    expect(Order::count())->toBe(0);
    expect(app(Cart::class)->items())->toBe([$product->id => 2]);
});

test('stock insuficiente lanza DomainException y rollback mantiene carrito', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 2, 'precio_cents' => 10000]);
    cartWithProduct($product, 2);
    $product->update(['stock' => 1]);

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute('Ana', 'ana@test.com', '1122334455', '1407', null, 'transferencia'))
        ->toThrow(DomainException::class);

    expect(Order::count())->toBe(0);
    expect(app(Cart::class)->items())->toBe([$product->id => 2]);
});

test('M2 calcula precioCaja via M2Calculator y total bcmath', function () {
    $product = Product::factory()->create([
        'unidad_venta' => 'm2',
        'precio_cents' => 100000,
        'm2_por_caja' => '1.15',
        'stock' => 10,
        'activo' => true,
    ]);
    // precioCaja = round(100000 * 1.15) = 115000
    expect($product->precioCajaCents())->toBe(115000);

    cartWithProduct($product, 2);

    $action = app(PlaceOrderAction::class);
    $order = $action->execute('M2User', 'm2@test.com', '1122334455', '1407', null, 'transferencia');

    expect($order->subtotal_cents)->toBe(230000);
    expect($order->total_cents)->toBe(230000); // shipping 0 sin tarifa
    $line = $order->lines->first();
    expect($line->precio_unitario_cents)->toBe(115000);
    expect($line->subtotal_cents)->toBe(230000);
    expect($line->m2_por_caja)->toBe('1.15');
});

test('Unidad calcula directo precio_cents', function () {
    $product = Product::factory()->unitMode()->create([
        'precio_cents' => 75000,
        'stock' => 10,
        'activo' => true,
    ]);

    cartWithProduct($product, 3);

    $order = app(PlaceOrderAction::class)->execute('Uni', 'uni@test.com', '1122334455', '1407', null, 'mercadopago');

    expect($order->subtotal_cents)->toBe(225000);
    expect($order->lines->first()->precio_unitario_cents)->toBe(75000);
    expect($order->payment_method)->toBe('mercadopago');
});

test('shipping disponible suma al total', function () {
    ShippingRate::factory()->create(['cp' => '1407', 'costo_cents' => 50000, 'activo' => true]);
    $product = Product::factory()->create(['precio_cents' => 100000, 'm2_por_caja' => '1.00', 'stock' => 10, 'activo' => true, 'unidad_venta' => 'm2']);
    cartWithProduct($product, 1); // 100000

    $order = app(PlaceOrderAction::class)->execute('Ship', 'ship@test.com', '1122334455', '1407', 'Dir 123', 'transferencia');

    expect($order->shipping_cost_cents)->toBe(50000);
    expect($order->shipping_cp)->toBe('1407');
    expect($order->total_cents)->toBe(150000);
});

test('shipping no disponible deja shipping_cost 0', function () {
    $product = Product::factory()->create(['precio_cents' => 50000, 'stock' => 10, 'activo' => true, 'unidad_venta' => 'unidad', 'm2_por_caja' => null]);
    cartWithProduct($product, 1);

    $order = app(PlaceOrderAction::class)->execute('NoShip', 'noship@test.com', '1122334455', '9999', null, 'transferencia');

    expect($order->shipping_cost_cents)->toBe(0);
    expect($order->total_cents)->toBe($order->subtotal_cents);
});

test('snapshot es independiente del producto posterior', function () {
    $product = Product::factory()->create(['name' => 'Orig', 'marca' => 'M1', 'specs' => ['color' => 'rojo'], 'precio_cents' => 100000, 'm2_por_caja' => '1.00', 'stock' => 10, 'activo' => true, 'unidad_venta' => 'm2']);
    cartWithProduct($product, 1);

    $order = app(PlaceOrderAction::class)->execute('Snap', 'snap@test.com', '1122334455', '1407', null, 'transferencia');
    $line = $order->lines->first();

    $product->update(['name' => 'Mod', 'marca' => 'M2', 'specs' => ['color' => 'azul'], 'precio_cents' => 999999]);

    $line->refresh();
    expect($line->product_name)->toBe('Orig');
    expect($line->marca)->toBe('M1');
    expect($line->specs)->toBe(['color' => 'rojo']);
    expect($line->precio_unitario_cents)->toBe(100000);
});

test('audit order.created con payload y sin descontar stock', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio_cents' => 50000, 'activo' => true]);
    $stockBefore = $product->stock;
    cartWithProduct($product, 2);

    $order = app(PlaceOrderAction::class)->execute('Audit', 'audit@test.com', '1122334455', '1407', null, 'transferencia');

    $log = AuditLog::where('action', 'order.created')->where('subject_type', Order::class)->where('subject_id', $order->id)->first();
    expect($log)->not->toBeNull();
    expect($log->payload['subtotal_cents'])->toBe($order->subtotal_cents);
    expect($log->payload['shipping_cost_cents'])->toBe($order->shipping_cost_cents);

    $product->refresh();
    expect($product->stock)->toBe($stockBefore); // no descuenta en Fase 2
});

test('commit limpia carrito, rollback mantiene carrito', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio_cents' => 50000, 'activo' => true]);
    cartWithProduct($product, 1);

    $order = app(PlaceOrderAction::class)->execute('Clear', 'clear@test.com', '1122334455', '1407', null, 'transferencia');
    expect(Order::count())->toBe(1);
    expect(app(Cart::class)->isEmpty())->toBeTrue();

    // rollback case
    $product2 = Product::factory()->create(['stock' => 1, 'precio_cents' => 50000, 'activo' => true]);
    cartWithProduct($product2, 1);
    $product2->update(['stock' => 0]);

    try {
        app(PlaceOrderAction::class)->execute('Fail', 'fail@test.com', '1122334455', '1407', null, 'transferencia');
    } catch (DomainException $e) {
        // expected
    }
    expect(Order::count())->toBe(1); // solo el anterior
    expect(app(Cart::class)->isEmpty())->toBeFalse();
    expect(app(Cart::class)->items())->toBe([$product2->id => 1]);
});

test('payment_method invalido lanza DomainException', function () {
    $product = Product::factory()->create(['stock' => 10, 'activo' => true]);
    cartWithProduct($product, 1);

    expect(fn () => app(PlaceOrderAction::class)->execute('Pay', 'pay@test.com', '1122334455', '1407', null, 'invalido'))
        ->toThrow(DomainException::class, 'El medio de pago no es válido.');
});

// HIG-07: este test NO reproduce concurrencia real y no pretende hacerlo. La suite
// corre con `RefreshDatabase`, que envuelve cada test en una transacción: una
// segunda conexión no vería estos datos y quedaría bloqueada en el lock, con el
// proceso de tests esperándola. La serialización de `lockForUpdate` la garantiza
// PostgreSQL; lo que acá se cubre es que dos pedidos sobre el mismo producto no
// se traban entre sí, y que la revalidación bajo lock funciona (tests de arriba).
// La Spec 07.2 quedó enmendada: afirmaba una cobertura de concurrencia inexistente.
test('dos pedidos sobre el mismo producto se ejecutan sin trabarse', function () {
    $product = Product::factory()->create(['stock' => 5, 'precio_cents' => 10000, 'activo' => true]);

    cartWithProduct($product, 2);
    $order1 = app(PlaceOrderAction::class)->execute('C1', 'c1@test.com', '1122334455', '1407', null, 'transferencia');

    cartWithProduct($product, 2);
    $order2 = app(PlaceOrderAction::class)->execute('C2', 'c2@test.com', '1122334455', '1407', null, 'transferencia');

    expect($order1->lines->first()->cantidad)->toBe(2);
    expect($order2->id)->not->toBe($order1->id);
    expect(Order::count())->toBe(2);

    // El stock no se descuenta en esta fase (ADR-005): baja al confirmarse el pago, Spec 08.
    expect($product->fresh()->stock)->toBe(5);
});

// HIG-06: la regla 108 exige que la Action valide los datos del cliente, no solo
// el carrito. Hoy se cumple por accidente, porque el único llamador entra por
// `StoreCheckoutRequest`; la Spec 08 suma llamadores que no pasan por HTTP.

test('email con formato invalido lanza DomainException y no crea pedido', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartWithProduct($product, 1);

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute('Juan', 'no-es-un-email', '1122334455', '1407', null, 'transferencia'))
        ->toThrow(DomainException::class, 'El email del cliente no es válido.');

    expect(Order::count())->toBe(0);
});

test('codigo postal que no matchea el regex lanza DomainException', function (string $cp) {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartWithProduct($product, 1);

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute('Juan', 'juan@test.com', '1122334455', $cp, null, 'transferencia'))
        ->toThrow(DomainException::class, 'El código postal no es válido.');

    expect(Order::count())->toBe(0);
})->with([
    'letras' => 'abc',
    'vacio' => '',
    'tres digitos' => '140',
    'cinco digitos' => '14077',
    'espacio interno' => '14 07',
]);

test('codigo postal con espacios alrededor se acepta tras el trim', function () {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartWithProduct($product, 1);

    $order = app(PlaceOrderAction::class)->execute('Juan', 'juan@test.com', '1122334455', '  1407  ', null, 'transferencia');

    expect($order->shipping_cp)->toBe('1407');
});

test('nombre o telefono vacios lanzan DomainException', function (string $name, string $phone, string $mensaje) {
    $product = Product::factory()->create(['activo' => true, 'stock' => 5, 'precio_cents' => 10000]);
    cartWithProduct($product, 1);

    $action = app(PlaceOrderAction::class);

    expect(fn () => $action->execute($name, 'juan@test.com', $phone, '1407', null, 'transferencia'))
        ->toThrow(DomainException::class, $mensaje);

    expect(Order::count())->toBe(0);
})->with([
    'nombre vacio' => ['', '1122334455', 'El nombre del cliente es obligatorio.'],
    'nombre solo espacios' => ['   ', '1122334455', 'El nombre del cliente es obligatorio.'],
    'telefono vacio' => ['Juan', '', 'El teléfono del cliente es obligatorio.'],
    'telefono solo espacios' => ['Juan', '   ', 'El teléfono del cliente es obligatorio.'],
]);
