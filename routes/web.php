<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShippingRateController;
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
Route::get('/checkout/exito', [CheckoutController::class, 'success'])->name('checkout.success');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

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

        Route::resource('tarifas-envio', ShippingRateController::class)
            ->parameters(['tarifas-envio' => 'tarifa_envio']);
    });
});

require __DIR__.'/auth.php';
