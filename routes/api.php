<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CorrespondenceController;
use App\Http\Controllers\Api\ObservatoryController;
use App\Http\Controllers\Api\PreAuthorizationController;
use App\Http\Controllers\Api\SupervisorFieldLogController;
use App\Http\Controllers\Api\SupervisorFieldSheetController;
use App\Http\Controllers\Api\SupervisorObservatoryController;
use App\Http\Controllers\Api\SupervisorShiftController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Public\ObservatoryApiDocsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/supervision/login', [SupervisorShiftController::class, 'login']);
Route::get('/observatory/openapi.json', [ObservatoryApiDocsController::class, 'spec']);

Route::middleware(['auth:sanctum', 'company.service'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('pre-authorizations', PreAuthorizationController::class)->except(['update']);
    Route::get('correspondence', [CorrespondenceController::class, 'index']);
    Route::get('correspondence/{correspondence}', [CorrespondenceController::class, 'show']);

    Route::get('visitors/search', [VisitorController::class, 'search']);

    Route::prefix('observatory')->group(function () {
        Route::get('/events', [ObservatoryController::class, 'events']);
        Route::get('/events/{event}', [ObservatoryController::class, 'show']);
        Route::get('/board', [ObservatoryController::class, 'board']);
        Route::get('/sites', [ObservatoryController::class, 'sites']);
        Route::post('/reports', [ObservatoryController::class, 'store'])->middleware('throttle:30,1');
    });

    Route::middleware('supervisor.pro')->prefix('supervision')->group(function () {
        Route::post('/password', [SupervisorShiftController::class, 'changePassword']);
        Route::get('/shifts/current', [SupervisorShiftController::class, 'current']);
        Route::get('/intake', [SupervisorShiftController::class, 'intake']);
        Route::get('/sites', [SupervisorShiftController::class, 'sites']);
        Route::get('/offline-pack', [SupervisorShiftController::class, 'offlinePack']);
        Route::get('/posts', [SupervisorShiftController::class, 'posts']);
        Route::get('/guards', [SupervisorShiftController::class, 'guards']);
        Route::get('/shift-photo/start-selfie', [SupervisorShiftController::class, 'startSelfie']);
        Route::post('/shifts/open', [SupervisorShiftController::class, 'open']);
        Route::post('/shifts/ping', [SupervisorShiftController::class, 'ping']);
        Route::post('/shifts/pings', [SupervisorShiftController::class, 'pings']);
        Route::post('/shifts/close', [SupervisorShiftController::class, 'close']);
        Route::post('/reviews', [SupervisorShiftController::class, 'review']);
        Route::post('/panic', [SupervisorShiftController::class, 'panic']);
        Route::get('/catalog', [SupervisorFieldLogController::class, 'catalog']);
        Route::post('/logs', [SupervisorFieldLogController::class, 'store']);
        Route::get('/observatory/sites', [SupervisorObservatoryController::class, 'sites']);
        Route::post('/observatory/reports', [SupervisorObservatoryController::class, 'store']);
        Route::get('/recommendations', [SupervisorFieldLogController::class, 'recommendations']);
        Route::get('/sheets', [SupervisorFieldSheetController::class, 'index']);
        Route::get('/sheets/{kind}/{id}', [SupervisorFieldSheetController::class, 'show']);
    });
});
