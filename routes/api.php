<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Prueba\Controllers\ActivityController;
use App\Modules\Prueba\Controllers\ActivityGroupController;
use App\Modules\Prueba\Controllers\ProcessController;
use App\Modules\Prueba\Controllers\OperatorController;
use App\Modules\Prueba\Controllers\AuthController;

// ── AUTH (pública) ─────────────────────────────────────
Route::post('/login',  [AuthController::class, 'login']);

// ── RUTAS PROTEGIDAS ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── ACTIVIDADES (ADMIN + SUPERVISOR) ──────────────
    Route::middleware('role:ADMIN,SUPERVISOR')->prefix('activities')->group(function () {
        Route::get('/history',             [ActivityController::class, 'history']);
        Route::get('/open',                [ActivityController::class, 'open']);
        Route::post('/start',              [ActivityController::class, 'start']);
        Route::post('/{id}/stop',          [ActivityController::class, 'stop']);
        Route::post('/{id}/stop-timer',    [ActivityController::class, 'stopTimer']);
        Route::post('/{id}/submit-report', [ActivityController::class, 'submitReport']);
        Route::post('/{id}/cancel',        [ActivityController::class, 'cancel']);
        Route::post('/{id}/note',          [ActivityController::class, 'addNote']);
        Route::get('/{id}/note',           [ActivityController::class, 'getNote']);
        Route::get('/dashboard',           [ActivityController::class, 'dashboard']);
        Route::get('/',                    [ActivityController::class, 'index']);
        Route::get('/{id}',                [ActivityController::class, 'show']);
        Route::post('/quick-report',       [ActivityController::class, 'quickReport']);
        Route::post('/report-manual',      [ActivityController::class, 'reportManual']);
    });

    // ── GRUPOS DE ACTIVIDAD (ADMIN + SUPERVISOR) ──────
    Route::middleware('role:ADMIN,SUPERVISOR')->prefix('activity-groups')->group(function () {
        Route::get('/open',                [ActivityGroupController::class, 'open']);
        Route::post('/start',              [ActivityGroupController::class, 'start']);
        Route::post('/{id}/stop-timer',    [ActivityGroupController::class, 'stopTimer']);
        Route::post('/{id}/submit-report', [ActivityGroupController::class, 'submitReport']);
        Route::post('/{id}/cancel',        [ActivityGroupController::class, 'cancel']);
        Route::post('/{id}/note',          [ActivityGroupController::class, 'addNote']);
    });

    // ── PROCESOS — lectura: ADMIN + SUPERVISOR ────────
    Route::middleware('role:ADMIN,SUPERVISOR')->prefix('processes')->group(function () {
        Route::get('/all', [ProcessController::class, 'all']);
        Route::get('/',    [ProcessController::class, 'index']);
    });

    // ── PROCESOS — escritura: solo ADMIN ──────────────
    Route::middleware('role:ADMIN')->prefix('processes')->group(function () {
        Route::post('/',             [ProcessController::class, 'store']);
        Route::put('/{id}',          [ProcessController::class, 'update']);
        Route::patch('/{id}/toggle', [ProcessController::class, 'toggle']);
        Route::delete('/{id}',       [ProcessController::class, 'destroy']);
    });

    // ── OPERADORES — lectura: ADMIN + SUPERVISOR ──────
    Route::middleware('role:ADMIN,SUPERVISOR')->prefix('operators')->group(function () {
        Route::get('/active', [OperatorController::class, 'active']);
        Route::get('/',       [OperatorController::class, 'index']);
    });

    // ── OPERADORES — escritura: solo ADMIN ────────────
    Route::middleware('role:ADMIN')->prefix('operators')->group(function () {
        Route::post('/',             [OperatorController::class, 'store']);
        Route::put('/{id}',          [OperatorController::class, 'update']);
        Route::patch('/{id}/toggle', [OperatorController::class, 'toggle']);
        Route::delete('/{id}',       [OperatorController::class, 'destroy']);
    });

});