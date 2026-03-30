<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Prueba\Models\Activity;
use App\Modules\Prueba\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


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
        $query = Activity::with([
            'operator:id,name',
            'process:id,name,base_per_hour',
            'supervisor:id,name'
        ])->whereIn('status', ['OPEN', 'CLOSED']);

        if ($request->filled('date_from'))   $query->whereDate('start_time', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('start_time', '<=', $request->date_to);
        if ($request->filled('operator_id')) $query->where('operator_id', $request->operator_id);
        if ($request->filled('process_id'))  $query->where('process_id',  $request->process_id);

        $activities = $query->orderBy('start_time', 'desc')->get();
        $closed     = $activities->where('status', 'CLOSED');

        // ── MÉTRICAS ────────────────────────────────────────────────────
        $metrics = [
            'total'                => $activities->count(),
            'open'                 => $activities->where('status', 'OPEN')->count(),
            'closed'               => $closed->count(),
            'total_quantity'       => $activities->sum('quantity'),
            'avg_duration_minutes' => $closed
                ->filter(fn($a) => $a->end_time && $a->start_time)
                ->avg(fn($a) => $a->start_time->diffInMinutes($a->end_time)) ?? 0,
        ];

        // ── HELPER: estándar ────────────────────────────────────────────
        $calcStandard = function ($activity): float {
            if (!$activity->end_time || !$activity->start_time) return 0.0;
            $minutes     = $activity->start_time->diffInSeconds($activity->end_time) / 60;
            $basePerHour = (float) ($activity->process->base_per_hour ?? 0);
            if ($basePerHour <= 0) return 0.0;
            return ($basePerHour / 60) * $minutes;
        };

        // ── EFECTIVIDAD POR OPERADOR ────────────────────────────────────
$byOperator = $closed
    ->groupBy('operator_id')
    ->map(function ($group) use ($calcStandard) {
        $real     = (float) $group->sum('quantity');
        $standard = (float) $group->sum(fn($a) => $calcStandard($a));

        // ✅ NUEVO: actividades individuales con efectividad por actividad
        $activities = $group->map(function ($a) use ($calcStandard) {
            $std      = $calcStandard($a);
            $qty      = (float) $a->quantity;
            $minutes  = ($a->start_time && $a->end_time)
                ? $a->start_time->diffInSeconds($a->end_time) / 60
                : 0;

            return [
                'id'           => $a->id,
                'name'         => $a->process->name ?? '—',
                // tiempo formateado legible: "45 min" o "1h 20min"
                'time'         => $minutes > 0
                    ? ($minutes >= 60
                        ? floor($minutes / 60) . 'h ' . ($minutes % 60) . 'min'
                        : round($minutes) . ' min')
                    : '—',
                'standard'     => round($std, 2),
                'real'         => $qty,
                // Efectividad por actividad = (Real / Estándar) × 100
                'effectiveness' => $std > 0
                    ? round(($qty / $std) * 100, 1)
                    : null,
            ];
        })->values();

        return [
            'operator_id'      => $group->first()->operator_id,
            'name'             => $group->first()->operator->name ?? '—',
            'activities_count' => $group->count(),
            'total_real'       => $real,
            'total_standard'   => round($standard, 2),
            'effectiveness'    => $standard > 0
                ? round(($real / $standard) * 100, 1)
                : null,
            'no_standard_data' => $standard <= 0,
            'activities'       => $activities, // ✅ esto alimenta el detalle del HTML
        ];
    })
    ->values();

        // ── EFECTIVIDAD POR PROCESO ─────────────────────────────────────
        // ✅ FIX: separado de nuevo, acceso directo a ->process->name
        $byProcess = $closed
            ->groupBy('process_id')
            ->map(function ($group) use ($calcStandard) {
                $real     = (float) $group->sum('quantity');
                $standard = (float) $group->sum(fn($a) => $calcStandard($a));
                return [
                    'process_id'       => $group->first()->process_id,
                    'name'             => $group->first()->process->name ?? '—',
                    'activities_count' => $group->count(),
                    'total_real'       => $real,
                    'total_standard'   => round($standard, 2),
                    'effectiveness'    => $standard > 0
                        ? round(($real / $standard) * 100, 1)
                        : null,
                    'no_standard_data' => $standard <= 0,
                ];
            })
            ->values();

        return response()->json([
            'activities'    => $activities,
            'metrics'       => $metrics,
            'effectiveness' => [
                'by_operator' => $byOperator,
                'by_process'  => $byProcess,
            ],
            // ✅ debug expandido — ahora muestra los valores calculados
            'debug' => app()->environment('local') ? [
                'closed_count'    => $closed->count(),
                'operators_found' => $byOperator->pluck('name'),
                'processes_found' => $byProcess->pluck('name'),
                // 🔍 NUEVO: muestra exactamente qué se calculó
                'effectiveness_sample' => $byOperator->map(fn($op) => [
                    'operator'       => $op['name'],
                    'real'           => $op['total_real'],
                    'standard'       => $op['total_standard'],
                    'effectiveness'  => $op['effectiveness'],
                    'no_std_data'    => $op['no_standard_data'],
                ]),
            ] : null,
        ]);
    }

    /**
 * 🔹 REPORTE MANUAL
 */
   public function reportManual(Request $request)
{
    $request->validate([
        'operator_id' => 'required|exists:users,id',
        'process_id'  => 'required|exists:processes,id',
        'start_time'  => 'required|date',
        'end_time'    => 'required|date|after:start_time',
        'quantity'    => 'required|integer|min:0',
    ]);

    try {
        $activity = Activity::create([
            'operator_id'   => $request->operator_id,
            'process_id'    => $request->process_id,
            'supervisor_id' => Auth::id(),
            'start_time'    => Carbon::parse($request->start_time, 'America/Caracas')->utc(),
            'end_time'      => Carbon::parse($request->end_time,   'America/Caracas')->utc(),
            'quantity'      => $request->quantity,
            'status'        => 'CLOSED',
        ]);

        return response()->json([
            'message' => 'Reporte registrado correctamente',
            'data'    => $activity->load(['operator', 'process'])
        ], 201);

         } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}