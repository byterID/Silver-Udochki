<?php

declare(strict_types=1);

use App\Enums\PermissionCode;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\ActionGroupController;
use App\Http\Controllers\ControlPanelController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
 * Панель управления.
 * Внешний слой — право на вход. Внутри — отдельное право на каждый раздел,
 * чтобы доступ к панели не означал автоматически доступ ко всему.
 */
Route::middleware(['auth', 'verified', 'permission:'.PermissionCode::AccessControlPanel->value])
    ->prefix('control-panel')
    ->name('control-panel.')
    ->group(function () {

        Route::get('/', [ControlPanelController::class, 'index'])->name('index');

        Route::middleware('permission:'.PermissionCode::ManageUsers->value)->group(function () {
            Route::get('/staff', [UserController::class, 'staff'])->name('staff');
            Route::get('/customers', [UserController::class, 'customers'])->name('customers');

            Route::middleware('throttle:30,1')->group(function () {
                Route::post('/users', [UserController::class, 'store'])->name('users.store');
                Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            });
        });

        Route::middleware('permission:'.PermissionCode::ManageAccess->value)->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
            Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
                ->name('permissions.update');

            Route::get('/access-control', [AccessControlController::class, 'index'])->name('access-control');
            Route::put('/access-control/{role}', [AccessControlController::class, 'update'])
                ->name('access-control.update');

            Route::get('/action-groups', [ActionGroupController::class, 'index'])->name('action-groups');
            Route::post('/action-groups', [ActionGroupController::class, 'store'])->name('action-groups.store');
            Route::put('/action-groups/{actionGroup}', [ActionGroupController::class, 'update'])
                ->name('action-groups.update');
        });
    });

require __DIR__.'/auth.php';
