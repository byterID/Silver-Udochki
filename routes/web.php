<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ControlPanelController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\ActionGroupController;

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

        // Действия в системе
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');

        // Управление доступом (матрица ролей и действий)
        Route::get('/access-control', [AccessControlController::class, 'index'])->name('access-control');
        Route::put('/access-control/{role}', [AccessControlController::class, 'update'])->name('access-control.update');

        // Группы действий
        Route::get('/action-groups', [ActionGroupController::class, 'index'])->name('action-groups');
        Route::post('/action-groups', [ActionGroupController::class, 'store'])->name('action-groups.store');
        Route::put('/action-groups/{actionGroup}', [ActionGroupController::class, 'update'])->name('action-groups.update');
    });

require __DIR__.'/auth.php';
