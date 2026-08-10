<?php

use App\Http\Controllers\Api\Agent\V1\AgentController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

Route::prefix('agent/v1')->middleware('throttle:agent')->group(function () {
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
