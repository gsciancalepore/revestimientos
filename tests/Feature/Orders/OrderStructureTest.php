<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('orders table existe con migraciones', function () {
    expect(DB::table('orders')->count())->toBe(0);
    expect(DB::table('order_lines')->count())->toBe(0);
});

test('order se crea con enum y casts en centavos', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::PendingPayment,
        'shipping_cost_cents' => 150000,
        'subtotal_cents' => 200000,
        'total_cents' => 350000,
    ]);

    expect($order->status)->toBe(OrderStatus::PendingPayment);
    expect($order->shipping_cost_cents)->toBe(150000);
    expect($order->subtotal_cents)->toBe(200000);
    expect($order->total_cents)->toBe(350000);
});

test('order status label es español', function () {
    expect(OrderStatus::PendingPayment->label())->toBe('Pendiente de pago');
    expect(OrderStatus::Paid->label())->toBe('Pagado');
    expect(OrderStatus::Shipped->label())->toBe('Despachado');
});

test('order scopes pendingPayment y paid', function () {
    Order::factory()->create(['status' => OrderStatus::PendingPayment]);
    Order::factory()->paid()->create();

    expect(Order::pendingPayment()->count())->toBe(1);
    expect(Order::paid()->count())->toBe(1);
});

test('order scopes byEmail y byStatus', function () {
    Order::factory()->create(['customer_email' => 'a@a.com', 'status' => OrderStatus::Shipped]);
    Order::factory()->create(['customer_email' => 'b@b.com', 'status' => OrderStatus::Shipped]);

    expect(Order::byEmail('a@a.com')->count())->toBe(1);
    expect(Order::byStatus(OrderStatus::Shipped)->count())->toBe(2);
});

test('order tiene many lines y order_line pertenece a order y product', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create();
    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'm2_por_caja' => '1.15',
        'specs' => ['medida' => '60x60'],
    ]);

    expect($order->lines)->toHaveCount(1);
    expect($line->order->id)->toBe($order->id);
    expect($line->product->id)->toBe($product->id);
    expect($line->m2_por_caja)->toBe('1.15');
    expect($line->specs)->toBe(['medida' => '60x60']);
});

test('order_line snapshot es independiente del producto posterior', function () {
    $product = Product::factory()->create(['name' => 'Original', 'marca' => 'Marca A', 'm2_por_caja' => '1.15']);
    $order = Order::factory()->create();
    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_codigo' => $product->codigo,
        'marca' => $product->marca,
        'unidad_venta' => $product->unidad_venta->value,
        'm2_por_caja' => $product->m2_por_caja,
        'cantidad' => 3,
        'precio_unitario_cents' => 50000,
        'subtotal_cents' => 150000,
        'specs' => ['color' => 'blanco'],
    ]);

    $product->update(['name' => 'Modificado', 'marca' => 'Marca B']);

    $line->refresh();

    expect($line->product_name)->toBe('Original');
    expect($line->marca)->toBe('Marca A');
    expect($line->specs)->toBe(['color' => 'blanco']);
});

test('order_line cantidad debe ser > 0 (CHECK)', function () {
    $order = Order::factory()->create();

    expect(fn () => DB::table('order_lines')->insert([
        'order_id' => $order->id,
        'product_id' => Product::factory()->create()->id,
        'product_name' => 'Test',
        'product_codigo' => 'COD-001',
        'unidad_venta' => 'm2',
        'cantidad' => 0,
        'precio_unitario_cents' => 10000,
        'subtotal_cents' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('order check impide shipping_cost_cents negativo', function () {
    expect(fn () => DB::table('orders')->insert([
        'status' => OrderStatus::PendingPayment->value,
        'customer_name' => 'Test',
        'customer_email' => 'test@test.com',
        'customer_phone' => '1122334455',
        'shipping_cp' => '1407',
        'shipping_cost_cents' => -1,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('order_line FK product restrictOnDelete', function () {
    $product = Product::factory()->create();
    $order = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

    expect(fn () => $product->delete())->toThrow(QueryException::class);
});

test('order_line cascadeOnDelete cuando se elimina order', function () {
    $order = Order::factory()->create();
    OrderLine::factory()->create(['order_id' => $order->id]);

    $order->delete();

    expect(OrderLine::query()->count())->toBe(0);
});
