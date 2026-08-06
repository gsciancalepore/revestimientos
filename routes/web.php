<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('catalogo.home');
Route::get('/catalogo', [CatalogController::class, 'catalogo'])->name('catalogo.index');
Route::get('/categorias/{categoria:slug}', [CatalogController::class, 'categoria'])->name('catalogo.categoria');
Route::get('/ofertas', [CatalogController::class, 'ofertas'])->name('catalogo.ofertas');
Route::get('/productos/{producto:slug}', [CatalogController::class, 'producto'])->name('catalogo.producto');

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
    });
});

require __DIR__.'/auth.php';
