<?php
// App/Modules/Prueba/Controllers/ActivityGroupController.php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Prueba\Models\Activity;
use App\Modules\Prueba\Models\ActivityGroup;
use App\Modules\Prueba\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityGroupController extends Controller
{
    /**
     * 🔹 INICIAR GRUPO (equivalente a start individual)
     * Crea el grupo + una Activity tracker por cada operador
     */
    public function start(Request $request)
    {
        $request->validate([
            'process_id'   => 'required|exists:processes,id',
            'operator_ids' => 'required|array|min:2',
            'operator_ids.*' => 'exists:users,id',
        ]);

        // Verificar que ningún operador tenga actividad activa
        $busy = Activity::whereIn('operator_id', $request->operator_ids)
            ->whereIn('status', ['OPEN', 'STOPPED'])
            ->with('operator')
            ->get();

        if ($busy->isNotEmpty()) {
            $names = $busy->map(fn($a) => $a->operator->name)->join(', ');
            return response()->json([
                'error' => "Los siguientes operadores ya tienen una actividad activa: {$names}"
            ], 400);
        }

        try {
            DB::beginTransaction();

            $now = now();

            // Crear grupo maestro
            $group = ActivityGroup::create([
                'process_id'    => $request->process_id,
                'supervisor_id' => Auth::id(),
                'start_time'    => $now,
                'status'        => 'OPEN',
            ]);

            // Crear actividad tracker por cada operador
            foreach ($request->operator_ids as $operatorId) {
                Activity::create([
                    'process_id'        => $request->process_id,
                    'operator_id'       => $operatorId,
                    'supervisor_id'     => Auth::id(),
                    'activity_group_id' => $group->id,
                    'start_time'        => $now,
                    'status'            => 'OPEN',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Actividad grupal iniciada',
                'data'    => $group->load(['process', 'activities.operator'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 DETENER TIMER DEL GRUPO
     */
    public function stopTimer(Request $request, $id)
{
    $group = ActivityGroup::findOrFail($id);

    if (!$group->isOpen()) {
        return response()->json(['error' => 'Solo grupos abiertos pueden detenerse'], 400);
    }

    // ── VALIDACIÓN: mínimo 2 minutos desde el inicio ──
    $minStopTime = Carbon::parse($group->start_time)->addMinutes(2);

    if (now()->lt($minStopTime)) {
        $remaining = now()->diffInSeconds($minStopTime);
        return response()->json([
            'error' => 'Deben transcurrir al menos 2 minutos antes de detener el tiempo',
            'remaining_seconds' => $remaining
        ], 422);
    }

    $group->stopTimer();

    return response()->json([
        'message' => 'Tiempo detenido',
        'data'    => $group->load(['process', 'activities.operator'])
    ]);
}

/**
 * 🔹 ENVIAR REPORTE DEL GRUPO
 */
public function submitReport(Request $request, $id)
{
    $request->validate([
        'quantity' => 'required|integer|min:0',
    ]);

    $group = ActivityGroup::with('process')->findOrFail($id);

    if (!$group->isStopped()) {
        return response()->json([
            'error' => 'El grupo debe estar detenido antes de enviar el reporte'
        ], 400);
    }

    // ── VALIDACIÓN: cantidad máxima 150% de la meta según tiempo ──
    $standard = $this->calcStandard($group);
    $quantityError = $this->validateMaxQuantity($request->quantity, $standard, 1.5);
    if ($quantityError) {
        return response()->json(['error' => $quantityError], 422);
    }

    $group->submitReport($request->quantity, $request->input('notes'));

    return response()->json([
        'message' => 'Reporte grupal enviado. Grupo listo para nuevo ciclo.',
        'data'    => $group->fresh()->load(['process', 'activities.operator'])
    ]);
}

/**
 * Calcula la producción estándar del grupo según tiempo transcurrido.
 * La base por hora del proceso es la misma, sin importar cuántos operadores haya.
 */
private function calcStandard($group): float
{
    if (!$group->end_time || !$group->start_time) return 0.0;

    $minutes = $group->start_time->diffInSeconds($group->end_time) / 60;
    $base    = (float) ($group->process->base_per_hour ?? 0);

    if ($base <= 0) return 0.0;

    return ($base / 60) * $minutes;
}

/**
 * Valida que la cantidad reportada no exceda el % permitido sobre la meta.
 */
private function validateMaxQuantity($quantity, $standard, $factor = 1.5): ?string
{
    $maxAllowed = $standard * $factor;
    if ($standard > 0 && $quantity > $maxAllowed) {
        return "La cantidad ($quantity) excede el 150% de la producción estándar calculada ($maxAllowed).";
    }
    return null;
}
    
    /**
     * 🔹 CANCELAR GRUPO
     */
    public function cancel($id)
    {
        $group = ActivityGroup::findOrFail($id);

        if (!$group->isActive()) {
            return response()->json(['error' => 'Solo grupos activos pueden cancelarse'], 400);
        }

        $group->cancel();

        return response()->json(['message' => 'Actividad grupal cancelada']);
    }

    /**
     * 🔹 LISTAR GRUPOS ACTIVOS
     */
    public function open(Request $request)
    {
        $user  = $request->user();
        $query = ActivityGroup::with(['process', 'activities.operator'])
            ->whereIn('status', ['OPEN', 'STOPPED'])
            ->orderBy('start_time', 'asc');

        if ($user && $user->role === 'SUPERVISOR') {
            $query->where('supervisor_id', $user->id);
        }

        return response()->json($query->get());
    }

    /**
     * 🔹 AGREGAR OBSERVACIÓN
     */
    public function addNote(Request $request, $id)
    {
        $request->validate(['notes' => 'required|string|max:1000']);

        $group = ActivityGroup::findOrFail($id);
        $group->update(['notes' => $request->notes]);

        return response()->json([
            'message' => 'Observación guardada',
            'data'    => $group->load(['process', 'activities.operator'])
        ]);
    }
}