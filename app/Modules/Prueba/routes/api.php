<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Prueba\Controllers\ActivityController;
use App\Modules\Prueba\Controllers\ProcessController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('activities')->group(function () {
    Route::post('/start', [ActivityController::class, 'start']);
    Route::post('/{id}/stop', [ActivityController::class, 'stop']);
    Route::post('/{id}/cancel', [ActivityController::class, 'cancel']);
    Route::get('/open', [ActivityController::class, 'open']);
    Route::get('/', [ActivityController::class, 'index']);
    Route::get('/{id}', [ActivityController::class, 'show']);
});

Route::get('/processes', [ProcessController::class, 'index']);