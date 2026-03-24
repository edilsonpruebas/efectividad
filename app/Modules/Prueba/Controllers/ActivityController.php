<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Prueba\Models\Activity;
use App\Modules\Prueba\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    /**
     * 🔹 START (FASE 1)
     */
    public function start(Request $request)
    {
        $request->validate([
            'process_id'  => 'required|exists:processes,id',
            'operator_id' => 'required|exists:users,id',
        ]);

        try {
            $activity = Activity::create([
                'process_id'    => $request->process_id,
                'operator_id'   => $request->operator_id,
                'supervisor_id' => Auth::id() ?? null,
                'start_time'    => now(),
                'status'        => 'OPEN'
            ]);

            if ($activity && $activity->id) {
                ActivityLog::create([
                    'activity_id' => $activity->id,
                    'action'      => 'START',
                    'user_id'     => Auth::id() ?? null,
                    'timestamp'   => now()
                ]);
            }

            return response()->json([
                'message' => 'Actividad iniciada',
                'data'    => $activity->load(['operator', 'process'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔹 STOP (FASE 2)
     */
    public function stop(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:0'
        ]);

        $activity = Activity::findOrFail($id);

        if (!$activity->isOpen()) {
            return response()->json(['error' => 'La actividad ya está cerrada'], 400);
        }

        $activity->close($request->quantity);

        return response()->json([
            'message' => 'Actividad finalizada',
            'data'    => $activity->load(['operator', 'process'])
        ]);
    }

    /**
     * 🔹 CANCELAR
     */
    public function cancel($id)
    {
        $activity = Activity::findOrFail($id);

        if (!$activity->isOpen()) {
            return response()->json(['error' => 'Solo actividades abiertas pueden cancelarse'], 400);
        }

        $activity->update(['status' => 'CANCELLED', 'end_time' => now()]);

        ActivityLog::create([
            'activity_id' => $activity->id,
            'action'      => 'CANCEL',
            'user_id'     => Auth::id() ?? null,
            'timestamp'   => now()
        ]);

        return response()->json(['message' => 'Actividad cancelada']);
    }

    /**
     * 🔹 LISTAR ABIERTAS
     */
    public function open()
    {
        $activities = Activity::with(['operator', 'process'])
            ->open()
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($activities);
    }

    /**
     * 🔹 HISTORIAL
     */
    public function history()
{
    return Activity::with(['operator', 'process'])
        ->where('status', 'CLOSED')
        ->orderBy('end_time', 'desc')
        ->limit(10) // ✅ solo últimos 10
        ->get()
        ->map(function ($activity) {
            $start = \Carbon\Carbon::parse($activity->start_time);
            $end   = \Carbon\Carbon::parse($activity->end_time);
            return [
                'id'               => $activity->id,
                'operator'         => $activity->operator->name,
                'process'          => $activity->process->name,
                'start_time'       => $activity->start_time,
                'end_time'         => $activity->end_time,
                'duration_minutes' => $start->diffInMinutes($end),
                'quantity'         => $activity->quantity, // ✅ agregar cantidad
            ];
        });
}

    /**
     * 🔹 INDEX
     */
    public function index(Request $request)
    {
        $query = Activity::with(['operator', 'process', 'supervisor']);

        if ($request->date)        $query->whereDate('start_time', $request->date);
        if ($request->operator_id) $query->where('operator_id', $request->operator_id);
        if ($request->process_id)  $query->where('process_id', $request->process_id);
        if ($request->status)      $query->where('status', $request->status);

        return response()->json($query->orderBy('start_time', 'desc')->get());
    }

    /**
     * 🔹 DETALLE
     */
    public function show($id)
    {
        $activity = Activity::with(['operator', 'process', 'supervisor', 'logs'])
            ->findOrFail($id);

        return response()->json($activity);
    }

    /**
     * 🔹 DASHBOARD DE EFECTIVIDAD
     * Efectividad = (Σ Real / Σ Estándar) × 100
     * Estándar = (base_per_hour / 60) × duración_en_minutos
     */
    public function dashboard(Request $request)
    {
        $query = Activity::with(['operator', 'process', 'supervisor'])
            ->whereIn('status', ['OPEN', 'CLOSED']);

        if ($request->date_from)   $query->whereDate('start_time', '>=', $request->date_from);
        if ($request->date_to)     $query->whereDate('start_time', '<=', $request->date_to);
        if ($request->operator_id) $query->where('operator_id', $request->operator_id);
        if ($request->process_id)  $query->where('process_id', $request->process_id);

        $activities = $query->orderBy('start_time', 'desc')->get();

        // ── MÉTRICAS GENERALES ──────────────────────────────────────────
        $closed = $activities->where('status', 'CLOSED');

        $metrics = [
            'total'                => $activities->count(),
            'open'                 => $activities->where('status', 'OPEN')->count(),
            'closed'               => $closed->count(),
            'total_quantity'       => $activities->sum('quantity'),
            'avg_duration_minutes' => $closed
                ->filter(fn($a) => $a->end_time && $a->start_time)
                ->avg(fn($a) => $a->start_time->diffInMinutes($a->end_time)) ?? 0,
        ];

        // ── HELPER: calcular estándar de una actividad ──────────────────
        // Estándar = (base_per_hour / 60) × minutos_trabajados
        $calcStandard = function ($activity) {
            if (!$activity->end_time || !$activity->start_time) return 0;
            $minutes     = $activity->start_time->diffInMinutes($activity->end_time);
            $basePerMin  = ($activity->process->base_per_hour ?? 0) / 60;
            return $basePerMin * $minutes;
        };

        // ── EFECTIVIDAD POR OPERADOR ────────────────────────────────────
        $byOperator = $closed
            ->groupBy('operator_id')
            ->map(function ($group) use ($calcStandard) {
                $real     = $group->sum('quantity');
                $standard = $group->sum(fn($a) => $calcStandard($a));
                return [
                    'operator_id'        => $group->first()->operator_id,
                    'name'               => $group->first()->operator->name ?? '—',
                    'activities_count'   => $group->count(),
                    'total_real'         => $real,
                    'total_standard'     => round($standard, 2),
                    'effectiveness'      => $standard > 0
                        ? round(($real / $standard) * 100, 1)
                        : null,
                ];
            })
            ->values();

        // ── EFECTIVIDAD POR PROCESO ─────────────────────────────────────
        $byProcess = $closed
            ->groupBy('process_id')
            ->map(function ($group) use ($calcStandard) {
                $real     = $group->sum('quantity');
                $standard = $group->sum(fn($a) => $calcStandard($a));
                return [
                    'process_id'       => $group->first()->process_id,
                    'name'             => $group->first()->process->name ?? '—',
                    'activities_count' => $group->count(),
                    'total_real'       => $real,
                    'total_standard'   => round($standard, 2),
                    'effectiveness'    => $standard > 0
                        ? round(($real / $standard) * 100, 1)
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'activities'    => $activities,
            'metrics'       => $metrics,
            'effectiveness' => [
                'by_operator' => $byOperator,
                'by_process'  => $byProcess,
            ]
        ]);
    }
}