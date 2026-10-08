<?php

declare(strict_types=1);

use App\Enums\PermissionCode;
use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\ActionGroupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ControlPanelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestInfoController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Задачи
 */
Route::middleware('auth')->group(function () {
    Route::prefix('tasks')->name('tasks.')->controller(TaskController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:5,1,tasks-store')->name('store');

        Route::middleware('can:view,task')->group(function () {
            Route::get('/{task}', 'show')->name('show');
            Route::get('/{task}/status', 'status')->middleware('throttle:120,1,tasks-status')->name('status');
            Route::get('/{task}/download', 'download')->middleware('throttle:30,1,tasks-download')->name('download');
        });
    });

    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->middleware('throttle:60,1,notifications')->name('index');
        Route::post('/read-all', 'readAll')->name('read-all');
        Route::post('/{id}/read', 'read')->name('read');
    });
});
/*
 * Магазин
 */
Route::get('/', [CatalogController::class, 'home'])->name('home');

Route::get('/catalog/{slug}', [CatalogController::class, 'category'])
    ->where('slug', '[a-z]+')
    ->name('catalog.category');

Route::get('/search', [CatalogController::class, 'search'])
    ->middleware('throttle:60,1')
    ->name('catalog.search');

Route::get('/request-info', RequestInfoController::class)
    ->middleware('throttle:60,1') // не больше 60 запросов в минуту с одного IP
    ->name('request-info');

// Отдельного дашборда нет: после входа и регистрации сразу в лавку.
Route::redirect('/dashboard', '/')->name('dashboard');

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
