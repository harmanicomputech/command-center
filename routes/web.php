<?php

use App\Http\Controllers\Console\AccountController;
use App\Http\Controllers\Console\AreaController;
use App\Http\Controllers\Console\AuditController;
use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\SearchController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\SystemController;
use App\Http\Controllers\Console\UserController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RunnerController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1')->name('setup');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::view('/offline', 'offline')->name('offline');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('manifest');

// For an external pinger on hosts without per-minute cron (URL on the System page).
Route::get('/cron/{token}', RunnerController::class)->middleware('throttle:30,1')->name('runner');

Route::middleware('auth')->group(function () {
    // The Field Force app (agents; coordinators can use it too).
    Route::prefix('field')->name('field.')->group(function () {
        Route::get('/', [FieldController::class, 'home'])->name('home');
        Route::get('/me', [FieldController::class, 'me'])->name('me');
        Route::get('/register', [FieldController::class, 'register'])->name('register');
        Route::get('/tasks', [FieldController::class, 'tasks'])->name('tasks');
        Route::get('/issues', [FieldController::class, 'issues'])->name('issues');
    });

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'password'])->middleware('throttle:10,1')->name('account.password');

    // The Command Center (coordinators and above).
    Route::middleware('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/search', SearchController::class)->middleware('throttle:120,1')->name('search');

        Route::get('/areas', [AreaController::class, 'index'])->name('areas');
        Route::get('/areas/{lga}', [AreaController::class, 'lga'])->name('areas.lga');
        Route::get('/areas/{lga}/{ward}', [AreaController::class, 'ward'])->name('areas.ward');

        Route::middleware('role:admin')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
            Route::put('/settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

            Route::get('/system', [SystemController::class, 'show'])->name('system');
            Route::post('/system/migrate', [SystemController::class, 'migrate'])->name('system.migrate');
            Route::post('/system/register', [SystemController::class, 'importRegister'])->name('system.register');
            Route::post('/system/register/confirm', [SystemController::class, 'confirmRegister'])->name('system.register.confirm');

            Route::get('/audit', [AuditController::class, 'index'])->name('audit');
            Route::view('/design', 'design.index')->name('design');
        });
    });
});
