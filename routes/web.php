<?php

use App\Http\Controllers\Api\FieldSyncController;
use App\Http\Controllers\Console\AccountController;
use App\Http\Controllers\Console\AreaController;
use App\Http\Controllers\Console\AuditController;
use App\Http\Controllers\Console\AuthController;
use App\Http\Controllers\Console\DashboardController;
use App\Http\Controllers\Console\EventController;
use App\Http\Controllers\Console\InfluencerController;
use App\Http\Controllers\Console\IssueController;
use App\Http\Controllers\Console\LeaderboardController;
use App\Http\Controllers\Console\PeopleController;
use App\Http\Controllers\Console\SearchController;
use App\Http\Controllers\Console\SettingsController;
use App\Http\Controllers\Console\StructureController;
use App\Http\Controllers\Console\SystemController;
use App\Http\Controllers\Console\TaskController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\UserController;
use App\Http\Controllers\Console\VoterController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RunnerController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
Route::post('/setup', [AuthController::class, 'setup'])->middleware('throttle:5,1')->name('setup');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::view('/offline', 'offline')->name('offline');
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/invite/{token}', [InviteController::class, 'show'])->middleware('throttle:30,1')->name('invite');
Route::post('/invite/{token}', [InviteController::class, 'accept'])->middleware('throttle:10,1')->name('invite.accept');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('manifest');

// For an external pinger on hosts without per-minute cron (URL on the System page).
Route::get('/cron/{token}', RunnerController::class)->middleware('throttle:30,1')->name('runner');

Route::middleware('auth')->group(function () {
    // The Field Force app (agents; coordinators can use it too).
    Route::prefix('field')->name('field.')->group(function () {
        Route::get('/', [FieldController::class, 'home'])->name('home');
        Route::get('/me', [FieldController::class, 'me'])->name('me');
        Route::get('/register', [FieldController::class, 'register'])->name('register');
        Route::post('/register', [FieldController::class, 'store'])->middleware('throttle:60,1')->name('register.store');
        Route::get('/outbox', [FieldController::class, 'outbox'])->name('outbox');
        Route::get('/registrations', [FieldController::class, 'registrations'])->name('registrations');
        Route::get('/tasks', [FieldController::class, 'tasks'])->name('tasks');
        Route::get('/tasks/{task}', [FieldController::class, 'task'])->name('task');
        Route::get('/issues', [FieldController::class, 'issues'])->name('issues');
        Route::get('/leaderboard', [FieldController::class, 'leaderboard'])->name('leaderboard');
    });

    // The Field Force outbox (session auth; the token route gives a fresh CSRF token).
    Route::get('/api/field/token', [FieldSyncController::class, 'token'])->name('field.token');
    Route::post('/api/field/sync', [FieldSyncController::class, 'sync'])->middleware('throttle:120,1')->name('field.sync');
    Route::post('/api/field/photos', [PhotoController::class, 'upload'])->middleware('throttle:60,1')->name('field.photos');
    Route::get('/photos/{photo}/{size?}', [PhotoController::class, 'show'])->whereIn('size', ['thumb'])->name('photos.show');

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

        Route::get('/people', [PeopleController::class, 'index'])->name('people');
        Route::get('/people/{person}', [PeopleController::class, 'show'])->name('people.show');
        Route::get('/structure', [StructureController::class, 'index'])->name('structure');
        Route::get('/influence', [InfluencerController::class, 'index'])->name('influencers');
        Route::post('/influence', [InfluencerController::class, 'store'])->name('influencers.store');
        Route::put('/influence/{influencer}', [InfluencerController::class, 'update'])->name('influencers.update');
        Route::delete('/influence/{influencer}', [InfluencerController::class, 'destroy'])->name('influencers.destroy');
        Route::get('/events', [EventController::class, 'index'])->name('events');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::post('/events/{event}/record', [EventController::class, 'record'])->name('events.record');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::post('/tasks/{task}/status', [TaskController::class, 'status'])->name('tasks.status');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::get('/issues', [IssueController::class, 'index'])->name('issues');
        Route::get('/issues/brief', [IssueController::class, 'brief'])->name('issues.brief');
        Route::post('/issues/{issue}/status', [IssueController::class, 'status'])->name('issues.status');
        Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
        Route::get('/leaderboard/review', [LeaderboardController::class, 'review'])->name('leaderboard.review');
        Route::post('/leaderboard/rewards', [LeaderboardController::class, 'reward'])->middleware('role:admin')->name('leaderboard.reward');
        Route::post('/events/{event}/photos', [PhotoController::class, 'storeForEvent'])->name('events.photos');

        Route::get('/voters', [VoterController::class, 'index'])->name('voters');
        Route::post('/voters/{voter}/verify', [VoterController::class, 'verify'])->name('voters.verify');
        Route::post('/voters/{voter}/invalid', [VoterController::class, 'invalidate'])->name('voters.invalidate');
        Route::post('/voters/{voter}/resolve', [VoterController::class, 'resolve'])->name('voters.resolve');

        Route::middleware('role:admin,lga_leader,ward_coordinator')->group(function () {
            Route::get('/team', [TeamController::class, 'index'])->name('team');
            Route::post('/team', [TeamController::class, 'store'])->middleware('throttle:30,1')->name('team.store');
            Route::post('/team/{member}/reinvite', [TeamController::class, 'reinvite'])->name('team.reinvite');
            Route::post('/team/{member}/revoke', [TeamController::class, 'revoke'])->name('team.revoke');
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('/voters/export', [VoterController::class, 'export'])->name('voters.export');
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
