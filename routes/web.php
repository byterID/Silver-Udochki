<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ControlPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/users', [UserController::class, 'index'])
    ->middleware(['auth', 'permission:manage_users']);

Route::put('/users/{user}/role', [UserController::class, 'updateRole'])
    ->middleware(['auth', 'permission:manage_users']);

Route::get('/promo', function () {
    return 'Секретная страница промокодов';
})->middleware(['auth', 'permission:create_promo']);

Route::middleware(['auth', 'permission:access_control_panel'])
    ->prefix('control-panel')
    ->name('control-panel.')
    ->group(function () {
        Route::get('/', [ControlPanelController::class, 'index'])->name('index');
        Route::get('/staff', [UserController::class, 'staff'])->name('staff');
        Route::get('/customers', [UserController::class, 'customers'])->name('customers');
        Route::post('/staff', [UserController::class, 'store'])->name('staff.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        // сюда потом добавишь promo, settings и т.д.
    });

require __DIR__.'/auth.php';
