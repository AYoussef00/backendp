<?php

use App\Http\Controllers\Api\Agent\V1\AgentController;
use App\Http\Controllers\InstallScriptController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FileController;
use App\Http\Controllers\Web\JobController;
use App\Http\Controllers\Web\ServerController;
use App\Http\Controllers\Web\WebsiteController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/install/{token}.sh', InstallScriptController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:30,1')
    ->name('install.script');

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('servers/create', [ServerController::class, 'create'])->name('servers.create');
    Route::post('servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('servers/{server}', [ServerController::class, 'show'])->name('servers.show');
    Route::delete('servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::post('servers/{server}/discover', [ServerController::class, 'discover'])->name('servers.discover');
    Route::post('servers/{server}/regenerate-token', [ServerController::class, 'regenerateToken'])->name('servers.regenerate-token');

    Route::get('websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::get('websites/{website}', [WebsiteController::class, 'show'])->name('websites.show');
    Route::post('websites/{website}/start', [WebsiteController::class, 'start'])->name('websites.start');
    Route::post('websites/{website}/stop', [WebsiteController::class, 'stop'])->name('websites.stop');
    Route::post('websites/{website}/restart', [WebsiteController::class, 'restart'])->name('websites.restart');
    Route::post('websites/{website}/enable', [WebsiteController::class, 'enable'])->name('websites.enable');
    Route::post('websites/{website}/disable', [WebsiteController::class, 'disable'])->name('websites.disable');

    Route::get('servers/{server}/files', [FileController::class, 'index'])->name('servers.files');
    Route::post('servers/{server}/files/read', [FileController::class, 'read'])->name('servers.files.read');
    Route::post('servers/{server}/files/write', [FileController::class, 'write'])->name('servers.files.write');
    Route::post('servers/{server}/files/delete', [FileController::class, 'destroy'])->name('servers.files.delete');
    Route::post('servers/{server}/services', [FileController::class, 'serviceAction'])->name('servers.services.action');

    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::get('servers/{server}/logs', [JobController::class, 'logs'])->name('servers.logs');
});

Route::prefix('api/agent/v1')->middleware('throttle:agent')->group(function () {
    Route::post('register', [AgentController::class, 'register'])->middleware('throttle:10,1');

    Route::middleware([AuthenticateAgent::class])->group(function () {
        Route::post('heartbeat', [AgentController::class, 'heartbeat']);
        Route::post('discovery', [AgentController::class, 'discovery']);
        Route::post('websites', [AgentController::class, 'websites']);
        Route::post('metrics', [AgentController::class, 'metrics']);
        Route::get('jobs', [AgentController::class, 'jobs']);
        Route::post('jobs/{job}/result', [AgentController::class, 'jobResult']);
    });
});

require __DIR__.'/settings.php';
