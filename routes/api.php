<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Prueba\Controllers\ActivityController;
use App\Modules\Prueba\Controllers\ActivityGroupController;
use App\Modules\Prueba\Controllers\ProcessController;
use App\Modules\Prueba\Controllers\OperatorController;
use App\Modules\Prueba\Controllers\AuthController;

// ── OPTIONS preflight ──────────────────────────────────
Route::options('{any}', function() {
    return response()->json([], 200);
})->where('any', '.*');

// ── AUTH (pública) ─────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);

// ── RUTAS PROTEGIDAS ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── DASHBOARD Y LISTADO ───────────────────────────
    Route::middleware('permission:dashboard.activities,reports.efectividad')->prefix('activities')->group(function () {
        Route::get('/dashboard', [ActivityController::class, 'dashboard']);
        Route::get('/open',      [ActivityController::class, 'open']);
        Route::get('/',          [ActivityController::class, 'index']);
        Route::get('/{id}',      [ActivityController::class, 'show']);
        Route::get('/{id}/note', [ActivityController::class, 'getNote']);
    });

    // ── HISTORIAL ─────────────────────────────────────
    Route::middleware('permission:reports.history')->prefix('activities')->group(function () {
        Route::get('/history', [ActivityController::class, 'history']);
    });

    // ── OPERACIÓN ─────────────────────────────────────
    Route::middleware('permission:dashboard.activities')->prefix('activities')->group(function () {
        Route::post('/start',              [ActivityController::class, 'start']);
        Route::post('/{id}/stop',          [ActivityController::class, 'stop']);
        Route::post('/{id}/stop-timer',    [ActivityController::class, 'stopTimer']);
        Route::post('/{id}/submit-report', [ActivityController::class, 'submitReport']);
        Route::post('/{id}/cancel',        [ActivityController::class, 'cancel']);
        Route::post('/{id}/note',          [ActivityController::class, 'addNote']);
        Route::post('/quick-report',       [ActivityController::class, 'quickReport']);
    });

    // ── REPORTE MANUAL ────────────────────────────────
    Route::middleware('permission:reports.manual')->prefix('activities')->group(function () {
        Route::post('/report-manual', [ActivityController::class, 'reportManual']);
    });

    // ── EDICIÓN Y AUDITORÍA ───────────────────────────
    Route::middleware('permission:admin.audit')->prefix('activities')->group(function () {
        Route::put('/{id}', [ActivityController::class, 'update']);
    });

    // ── GRUPOS DE ACTIVIDAD ───────────────────────────
    Route::middleware('permission:dashboard.activities')->prefix('activity-groups')->group(function () {
        Route::get('/open',                [ActivityGroupController::class, 'open']);
        Route::post('/start',              [ActivityGroupController::class, 'start']);
        Route::post('/{id}/stop-timer',    [ActivityGroupController::class, 'stopTimer']);
        Route::post('/{id}/submit-report', [ActivityGroupController::class, 'submitReport']);
        Route::post('/{id}/cancel',        [ActivityGroupController::class, 'cancel']);
        Route::post('/{id}/note',          [ActivityGroupController::class, 'addNote']);
    });

    // ── OPERADORES Y PROCESOS — lectura para reportes ─
    // FABRICA, SUPERVISOR, RRHH, VP, ADMIN
    Route::middleware('permission:reports.data')->group(function () {
        Route::get('/operators/active', [OperatorController::class, 'active']);
        Route::get('/operators',        [OperatorController::class, 'index']);
        Route::get('/processes/all',    [ProcessController::class, 'all']);
        Route::get('/processes',        [ProcessController::class, 'index']);
    });

    // ── OPERADORES — escritura ────────────────────────
    // Solo RRHH, VP, ADMIN
    Route::middleware('permission:admin.operators')->prefix('operators')->group(function () {
        Route::post('/',             [OperatorController::class, 'store']);
        Route::put('/{id}',          [OperatorController::class, 'update']);
        Route::patch('/{id}/toggle', [OperatorController::class, 'toggle']);
        Route::delete('/{id}',       [OperatorController::class, 'destroy']);
    });

    // ── PROCESOS — escritura ──────────────────────────
    // Solo RRHH, VP, ADMIN
    Route::middleware('permission:admin.audit')->prefix('processes')->group(function () {
        Route::post('/',             [ProcessController::class, 'store']);
        Route::put('/{id}',          [ProcessController::class, 'update']);
        Route::patch('/{id}/toggle', [ProcessController::class, 'toggle']);
        Route::delete('/{id}',       [ProcessController::class, 'destroy']);
    });

});