<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Prueba\Controllers\ActivityController;
use App\Modules\Prueba\Controllers\ProcessController;
use App\Modules\Prueba\Controllers\OperatorController;  // ← agregado
use App\Modules\Prueba\Models\User;

// Ruta de usuario autenticado
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── ACTIVIDADES ───────────────────────────────────────
Route::prefix('activities')->group(function () {
    Route::get('/history',        [ActivityController::class, 'history']);
    Route::get('/open',           [ActivityController::class, 'open']);
    Route::post('/start',         [ActivityController::class, 'start']);
    Route::post('/report-manual', [ActivityController::class, 'reportManual']);
    Route::post('/{id}/stop',     [ActivityController::class, 'stop']);
    Route::post('/{id}/cancel',   [ActivityController::class, 'cancel']);
    Route::post('/{id}/note',  [ActivityController::class, 'addNote']);
    Route::get('/{id}/note',   [ActivityController::class, 'getNote']);
    Route::get('/dashboard',      [ActivityController::class, 'dashboard']);
    Route::get('/',               [ActivityController::class, 'index']);
    Route::get('/{id}',           [ActivityController::class, 'show']);
});

// ── PROCESOS ──────────────────────────────────────────
Route::prefix('processes')->group(function () {
    Route::get('/all',           [ProcessController::class, 'all']);      // todos
    Route::get('/',              [ProcessController::class, 'index']);     // solo activos (original)
    Route::post('/',             [ProcessController::class, 'store']);
    Route::put('/{id}',          [ProcessController::class, 'update']);
    Route::patch('/{id}/toggle', [ProcessController::class, 'toggle']);
    Route::delete('/{id}',       [ProcessController::class, 'destroy']);
});

// ── OPERADORES ────────────────────────────────────────
Route::prefix('operators')->group(function () {
    Route::get('/active',        [OperatorController::class, 'active']);   // solo activos
    Route::get('/',              [OperatorController::class, 'index']);     // todos
    Route::post('/',             [OperatorController::class, 'store']);
    Route::put('/{id}',          [OperatorController::class, 'update']);
    Route::patch('/{id}/toggle', [OperatorController::class, 'toggle']);
    Route::delete('/{id}',       [OperatorController::class, 'destroy']);
});