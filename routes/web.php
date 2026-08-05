<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('role:admin')->group(function () {
        Route::resource('usuarios', UserController::class)->except(['show']);
        Route::patch('usuarios/{user}/active', [UserController::class, 'toggleActive'])
            ->name('usuarios.toggle-active');
    });
});

require __DIR__.'/auth.php';
