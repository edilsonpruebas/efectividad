<?php 

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Prueba\Controllers\ActivityController;
use App\Modules\Prueba\Controllers\ProcessController;
use App\Modules\Prueba\Models\User;

// Ruta de usuario autenticado
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas de actividades
Route::prefix('activities')->group(function () {

    // 🔥 RUTAS ESPECÍFICAS PRIMERO
    Route::get('/history', [ActivityController::class, 'history']);
    Route::get('/open',    [ActivityController::class, 'open']);

    // 🔹 ACCIONES
    Route::post('/start',       [ActivityController::class, 'start']);
    Route::post('/{id}/stop',   [ActivityController::class, 'stop']);
    Route::post('/{id}/cancel', [ActivityController::class, 'cancel']);

    // 🔹 DASHBOARD
    Route::get('/dashboard',     [ActivityController::class, 'dashboard']);

    // 🔹 GENERALES
    Route::get('/',       [ActivityController::class, 'index']);
    Route::get('/{id}',   [ActivityController::class, 'show']); // 🔥 SIEMPRE DE ÚLTIMA
});

// Procesos
Route::get('/processes', [ProcessController::class, 'index']);

// Operadores
Route::get('/operators', function () {
    return User::operators()->get(['id', 'name']);
});