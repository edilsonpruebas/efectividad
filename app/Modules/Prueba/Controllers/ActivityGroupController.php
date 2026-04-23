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

        $group = ActivityGroup::findOrFail($id);

        if (!$group->isStopped()) {
            return response()->json([
                'error' => 'El grupo debe estar detenido antes de enviar el reporte'
            ], 400);
        }

        $group->submitReport($request->quantity, $request->input('notes'));

        return response()->json([
            'message' => 'Reporte grupal enviado. Grupo listo para nuevo ciclo.',
            'data'    => $group->fresh()->load(['process', 'activities.operator'])
        ]);
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