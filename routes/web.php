<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DispatchController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShippingRateController;
use App\Http\Controllers\ShippingRateImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('catalogo.home');
Route::get('/catalogo', [CatalogController::class, 'catalogo'])->name('catalogo.index');
Route::get('/categorias/{categoria:slug}', [CatalogController::class, 'categoria'])->name('catalogo.categoria');
Route::get('/ofertas', [CatalogController::class, 'ofertas'])->name('catalogo.ofertas');
Route::get('/productos/{producto:slug}', [CatalogController::class, 'producto'])->name('catalogo.producto');

Route::get('/carrito', [CartController::class, 'show'])->name('carrito.show');
Route::post('/carrito/agregar', [CartController::class, 'add'])->name('carrito.add');
Route::patch('/carrito/{producto:slug}', [CartController::class, 'update'])->name('carrito.update');
Route::delete('/carrito/{producto:slug}', [CartController::class, 'remove'])->name('carrito.remove');
Route::delete('/carrito', [CartController::class, 'clear'])->name('carrito.clear');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::post('/checkout/mercadopago/reintentar', [CheckoutController::class, 'retryMercadoPago'])->name('checkout.mercadopago.retry');
Route::get('/checkout/exito', [CheckoutController::class, 'success'])->name('checkout.success');

// Sin auth y sin sesión: lo autentica la firma de MercadoPago (Spec 08, reglas 153 y 154).
Route::post('/webhook/mercadopago', MercadoPagoWebhookController::class)->name('webhook.mercadopago');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Pedidos y despacho autorizan por Policy, no por rol de middleware: cada
    // acción tiene su propia fila en la matriz de permisos de la Spec 08 (el
    // vendedor ve pedidos y no cobra; el depósito despacha y no ve plata).
    Route::get('pedidos', [OrderController::class, 'index'])->name('pedidos.index');
    Route::get('pedidos/{pedido}', [OrderController::class, 'show'])->name('pedidos.show');
    Route::post('pedidos/{pedido}/confirmar-pago', [OrderController::class, 'confirmPayment'])->name('pedidos.confirmar-pago');
    Route::post('pedidos/{pedido}/cancelar', [OrderController::class, 'cancel'])->name('pedidos.cancelar');

    Route::get('despacho', [DispatchController::class, 'index'])->name('despacho.index');
    Route::post('despacho/{pedido}/despachar', [DispatchController::class, 'ship'])->name('despacho.despachar');
    Route::post('despacho/{pedido}/entregar', [DispatchController::class, 'deliver'])->name('despacho.entregar');

    Route::middleware('role:admin')->group(function () {
        Route::resource('usuarios', UserController::class)
            ->except(['show'])
            ->parameters(['usuarios' => 'user']);
        Route::patch('usuarios/{user}/active', [UserController::class, 'toggleActive'])
            ->name('usuarios.toggle-active');

        Route::resource('categorias', CategoryController::class)
            ->except(['show'])
            ->parameters(['categorias' => 'category']);

        Route::resource('productos', ProductController::class)
            ->except(['show'])
            ->parameters(['productos' => 'product']);

        Route::get('tarifas-envio/importar', [ShippingRateImportController::class, 'import'])
            ->name('tarifas-envio.import');
        Route::post('tarifas-envio/importar', [ShippingRateImportController::class, 'upload'])
            ->name('tarifas-envio.import.upload');
        Route::get('tarifas-envio/importar/preview', [ShippingRateImportController::class, 'preview'])
            ->name('tarifas-envio.import.preview');
        Route::post('tarifas-envio/importar/confirmar', [ShippingRateImportController::class, 'confirm'])
            ->name('tarifas-envio.import.confirm');
        Route::post('tarifas-envio/importar/cancelar', [ShippingRateImportController::class, 'cancel'])
            ->name('tarifas-envio.import.cancel');

        Route::resource('tarifas-envio', ShippingRateController::class)
            ->parameters(['tarifas-envio' => 'tarifa_envio']);
    });
});

require __DIR__.'/auth.php';
