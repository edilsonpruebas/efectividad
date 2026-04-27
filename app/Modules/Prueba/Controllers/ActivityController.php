<?php
 
namespace App\Modules\Prueba\Controllers;
 
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Prueba\Models\Activity;
use App\Modules\Prueba\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Modules\Prueba\Models\ActivityGroup;
use Illuminate\Support\Facades\DB;
 
 
class ActivityController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ══════════════════════════════════════════════════════════════════════
 
    /**
     * Calcula la producción estándar esperada de una actividad.
     * Estándar = (base_per_hour / 60) × duración_en_minutos
     */
   private function calcStandard($activity): float
{
    if (!$activity->end_time || !$activity->start_time) return 0.0;
    $minutes = $activity->start_time->diffInSeconds($activity->end_time) / 60;
    $base    = (float) ($activity->process->base_per_hour ?? 0);
    if ($base <= 0) return 0.0;
    return ($base / 60) * $minutes;
}
 
    /**
     * Calcula el promedio ponderado multiactividad de un grupo de actividades.
     *
     * Fórmula (igual que Excel):
     *   CA       = Σ horas de todas las actividades
     *   peso_i   = horas_actividad_i / CA
     *   aporte_i = efectividad_i × peso_i
     *   E_total  = Σ aporte_i
     *
     * El peso es proporcional SOLO al tiempo (no a la base), replicando
     * exactamente la fórmula =((F+G/60)/$CA$)*(J/100) del Excel.
     */
    private function calcWeighted($group): float
    {
        // CA: total de horas de todas las actividades del operador
        $totalHoras = $group->sum(function ($a) {
            if (!$a->start_time || !$a->end_time) return 0;
            return $a->start_time->diffInSeconds($a->end_time) / 3600;
        });
 
        if ($totalHoras <= 0) return 0.0;
 
        return $group->sum(function ($a) use ($totalHoras) {
            $std   = $this->calcStandard($a);
            $qty   = (float) $a->quantity;
            $horas = ($a->start_time && $a->end_time)
                ? $a->start_time->diffInSeconds($a->end_time) / 3600
                : 0;
 
            $efectividad = $std > 0 ? ($qty / $std) : 0;
            $peso        = $horas / $totalHoras;
 
            return $efectividad * $peso;
        });
    }
 
 
    // ══════════════════════════════════════════════════════════════════════
    // ENDPOINTS
    // ══════════════════════════════════════════════════════════════════════
 
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

        try {
            ActivityLog::create([
                'activity_id' => $activity->id,
                'action'      => 'START',
                'user_id'     => Auth::id() ?? null,
                'timestamp'   => now()
            ]);
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'Actividad iniciada',
            'data'    => $activity->load(['operator', 'process'])
        ], 201);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
     }
    }
 
    /**
 * 🔹 STOP TIMER (FASE 2) — Detiene el cronómetro
 */
public function stopTimer(Request $request, $id)
{
    $activity = Activity::findOrFail($id);

    if (!$activity->isOpen()) {
        return response()->json(['error' => 'Solo actividades abiertas pueden detenerse'], 400);
    }

    $activity->stopTimer();

    return response()->json([
        'message' => 'Tiempo detenido',
        'data'    => $activity->load(['operator', 'process'])
    ]);
}

/**
 * 🔹 SUBMIT REPORT (FASE 3)
 * Guarda el reporte, deja la actividad activa y resetea start_time
 */
public function submitReport(Request $request, $id)
{
    $request->validate([
        'quantity' => 'required|integer|min:0',
    ]);

    // ✅ Buscar Activity, no ActivityGroup
    $activity = Activity::findOrFail($id);

    if (!$activity->isStopped()) {
        return response()->json([
            'error' => 'La actividad debe estar detenida antes de enviar el reporte'
        ], 400);
    }

    $activity->submitReport($request->quantity, $request->input('notes'));

    return response()->json([
        'message' => 'Reporte enviado. Actividad lista para nuevo ciclo.',
        'data'    => $activity->fresh()->load(['operator', 'process'])
    ]);
}

/**
 * 🔹 STOP (FASE 2 — LEGACY, mantener por compatibilidad)
 * Ahora hace stopTimer + submitReport en uno solo si se necesita
 */
public function stop(Request $request, $id)
{
    $request->validate([
        'quantity' => 'required|integer|min:0'
    ]);

    $activity = Activity::findOrFail($id);

    // Si está OPEN, detener primero
    if ($activity->isOpen()) {
        $activity->stopTimer();
    }

    if (!$activity->isStopped()) {
        return response()->json(['error' => 'La actividad ya está cerrada'], 400);
    }

    $activity->close($request->quantity);

    return response()->json([
        'message' => 'Actividad finalizada',
        'data'    => $activity->load(['operator', 'process'])
    ]);
}

/**
 * 🔹 QUICK REPORT — Nuevo reporte para operador con mismo proceso ya cerrado
 * Crea actividad nueva con start_time y end_time = now()
 */
public function quickReport(Request $request)
{
    $request->validate([
        'operator_id' => 'required|exists:users,id',
        'process_id'  => 'required|exists:processes,id',
        'quantity'    => 'required|integer|min:0',
    ]);

    // Verificar que no tenga actividad activa
    $hasActive = Activity::where('operator_id', $request->operator_id)
        ->whereIn('status', ['OPEN', 'STOPPED'])
        ->exists();

    if ($hasActive) {
        return response()->json(['error' => 'El operador tiene una actividad activa en curso'], 400);
    }

    try {
        $now = now();

        $activity = Activity::create([
            'process_id'    => $request->process_id,
            'operator_id'   => $request->operator_id,
            'supervisor_id' => Auth::id() ?? null,
            'start_time'    => $now,
            'end_time'      => $now,
            'quantity'      => $request->quantity,
            'status'        => 'CLOSED',
        ]);

        if ($request->filled('notes')) {
            $activity->update(['notes' => $request->notes]);
        }

        ActivityLog::create([
            'activity_id' => $activity->id,
            'action'      => 'QUICK_REPORT',
            'user_id'     => Auth::id() ?? null,
            'timestamp'   => $now,
        ]);

        return response()->json([
            'message' => 'Reporte rápido enviado',
            'data'    => $activity->load(['operator', 'process'])
        ], 201);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    /**
     * 🔹 CANCELAR
     */
    public function cancel($id)
{
    $activity = Activity::findOrFail($id);

    if (!$activity->isActive()) {
        return response()->json(['error' => 'Solo actividades abiertas pueden cancelarse'], 400);
    }

    $activity->cancel();

    return response()->json([
        'message' => 'Actividad cancelada'
    ]);
}
 
    /**
     * 🔹 LISTAR ABIERTAS
     */
   public function open(Request $request)
{
    $user  = $request->user();
    $query = Activity::with(['operator', 'process'])
                     ->whereIn('status', ['OPEN', 'STOPPED'])
                     ->whereNull('activity_group_id') // ← excluir trackers de grupos
                     ->orderBy('start_time', 'asc');

    if ($user && $user->role === 'SUPERVISOR') {
        $query->where('supervisor_id', $user->id);
    }

    return response()->json($query->get());
}
    /**
     * 🔹 HISTORIAL
     */
    public function history(Request $request)
{
    $user  = $request->user();
    $query = Activity::with(['operator', 'process'])
                     ->where('status', 'CLOSED')
                     ->orderBy('end_time', 'desc')
                     ->limit(50);

    if ($user && $user->role === 'SUPERVISOR') {
        $query->where('supervisor_id', $user->id);
    }

    return $query->get()->map(function ($activity) {
        $start = \Carbon\Carbon::parse($activity->start_time);
        $end   = \Carbon\Carbon::parse($activity->end_time);
        return [
            'id'               => $activity->id,
            'operator'         => $activity->operator->name,
            'process'          => $activity->process->name,
            'start_time'       => $activity->start_time,
            'end_time'         => $activity->end_time,
            'duration_minutes' => $start->diffInMinutes($end),
            'quantity'         => $activity->quantity,
        ];
    });
}
 
    /**
     * 🔹 INDEX
     */
   /**
     * 🔹 INDEX — Reemplaza el método index() existente
     * Agrega: carga supervisor, logs.user, y campos de auditoría
     */
    public function index(Request $request)
    {
        $query = Activity::with([
            'operator:id,name',
            'process:id,name,base_per_hour',
            'supervisor:id,name',   // ← "Enviado por"
            'logs.user:id,name',    // ← historial de acciones con nombre de usuario
        ]);
 
        if ($request->date)        $query->whereDate('start_time', $request->date);
        if ($request->operator_id) $query->where('operator_id', $request->operator_id);
        if ($request->process_id)  $query->where('process_id', $request->process_id);
        if ($request->status)      $query->where('status', $request->status);
 
        return response()->json(
            $query->orderBy('start_time', 'desc')->get()
        );
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

    // Separar individuales de grupales
    $closedIndividual = $closed->where('is_group_member', false);
    $closedGrouped    = $closed->where('is_group_member', true);

    // ── MÉTRICAS ──────────────────────────────────────────────────────
    $groupQuantities = $closedGrouped
        ->unique('activity_group_id')
        ->sum('quantity');

    $metrics = [
        'total'                => $activities->count(),
        'open'                 => $activities->where('status', 'OPEN')->count(),
        'closed'               => $closed->count(),
        'total_quantity'       => $closedIndividual->sum('quantity') + $groupQuantities,
        'avg_duration_minutes' => $closed
            ->filter(fn($a) => $a->end_time && $a->start_time)
            ->avg(fn($a) => $a->start_time->diffInMinutes($a->end_time)) ?? 0,
    ];

    // ── EFECTIVIDAD POR OPERADOR ──────────────────────────────────────
    $byOperator = $closed
        ->groupBy('operator_id')
        ->map(function ($group) {
            $real     = (float) $group->sum('quantity');
            $standard = (float) $group->sum(fn($a) => $this->calcStandard($a));

            $activitiesDetail = $group->map(function ($a) {
                $std     = $this->calcStandard($a);
                $qty     = (float) $a->quantity;
                $minutes = ($a->start_time && $a->end_time)
                    ? $a->start_time->diffInSeconds($a->end_time) / 60
                    : 0;

                return [
                    'id'            => $a->id,
                    'name'          => $a->process->name ?? '—',
                    'is_group'      => (bool) $a->is_group_member,
                    'time'          => $minutes > 0
                        ? ($minutes >= 60
                            ? floor($minutes / 60) . 'h ' . ($minutes % 60) . 'min'
                            : round($minutes) . ' min')
                        : '—',
                    'standard'      => round($std, 2),
                    'real'          => $qty,
                    'effectiveness' => $std > 0
                        ? round(($qty / $std) * 100, 1)
                        : null,
                ];
            })->values();

            return [
                'operator_id'            => $group->first()->operator_id,
                'name'                   => $group->first()->operator->name ?? '—',
                'activities_count'       => $group->count(),
                'total_real'             => $real,
                'total_standard'         => round($standard, 2),
                'effectiveness'          => $standard > 0
                    ? round(($real / $standard) * 100, 1)
                    : null,
                'no_standard_data'       => $standard <= 0,
                'weighted_effectiveness' => round($this->calcWeighted($group) * 100, 1),
                'activities'             => $activitiesDetail,
            ];
        })
        ->values();

    // ── EFECTIVIDAD POR PROCESO ───────────────────────────────────────
    $byProcess = $closed
    ->groupBy('process_id')
    ->map(function ($group) {

        // ── SEPARAR ─────────────────────────────
        $individual = $group->where('is_group_member', false);
        $grouped    = $group->where('is_group_member', true);

        // 🔥 UNA SOLA ACTIVIDAD POR GRUPO
        $groupedUnique = $grouped->unique('activity_group_id');

        // ── REAL ────────────────────────────────
        $real =
            (float) $individual->sum('quantity') +
            (float) $groupedUnique->sum('quantity');

        // ── STANDARD (🔥 FIX CLAVE) ─────────────
        $standard =
            (float) $individual->sum(fn($a) => $this->calcStandard($a)) +
            (float) $groupedUnique->sum(fn($a) => $this->calcStandard($a));

        // ── HORAS ───────────────────────────────
        $totalHoras =
            $individual->sum(function ($a) {
                if (!$a->start_time || !$a->end_time) return 0;
                return $a->start_time->diffInSeconds($a->end_time) / 3600;
            }) +
            $groupedUnique->sum(function ($a) {
                if (!$a->start_time || !$a->end_time) return 0;
                return $a->start_time->diffInSeconds($a->end_time) / 3600;
            });

        // ── WEIGHTED ────────────────────────────
        $weighted = 0;

        if ($totalHoras > 0) {

            // INDIVIDUALES
            $weighted += $individual->sum(function ($a) use ($totalHoras) {
                $std   = $this->calcStandard($a);
                $qty   = (float) $a->quantity;

                $horas = ($a->start_time && $a->end_time)
                    ? $a->start_time->diffInSeconds($a->end_time) / 3600
                    : 0;

                $efectividad = $std > 0 ? ($qty / $std) : 0;
                $peso        = $horas / $totalHoras;

                return $efectividad * $peso;
            });

            // GRUPALES (SIN DUPLICAR)
            $weighted += $groupedUnique->sum(function ($a) use ($totalHoras) {
                $std   = $this->calcStandard($a);
                $qty   = (float) $a->quantity;

                $horas = ($a->start_time && $a->end_time)
                    ? $a->start_time->diffInSeconds($a->end_time) / 3600
                    : 0;

                $efectividad = $std > 0 ? ($qty / $std) : 0;
                $peso        = $horas / $totalHoras;

                return $efectividad * $peso;
            });
        }

        return [
            'process_id'             => $group->first()->process_id,
            'name'                   => $group->first()->process->name ?? '—',

            // 🔥 FIX COUNT
            'activities_count'       =>
                $individual->count() + $groupedUnique->count(),

            'total_real'             => $real,
            'total_standard'         => round($standard, 2),
            'total_horas'            => round($totalHoras, 4),

            // 🔥 AHORA SÍ CORRECTO
            'effectiveness'          => $standard > 0
                ? round(($real / $standard) * 100, 1)
                : null,

            'weighted_effectiveness' => round($weighted * 100, 1),
            'no_standard_data'       => $standard <= 0,
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
        'debug' => app()->environment('local') ? [
            'closed_count'         => $closed->count(),
            'operators_found'      => $byOperator->pluck('name'),
            'processes_found'      => $byProcess->pluck('name'),
            'effectiveness_sample' => $byOperator->map(fn($op) => [
                'operator'               => $op['name'],
                'real'                   => $op['total_real'],
                'standard'               => $op['total_standard'],
                'effectiveness'          => $op['effectiveness'],
                'weighted_effectiveness' => $op['weighted_effectiveness'],
                'no_std_data'            => $op['no_standard_data'],
            ]),
        ] : null,
    ]);
}
 
    /**
     * 🔹 EFECTIVIDAD PONDERADA (PESO DINÁMICO)
     *
     * Fórmula (replica Excel):
     *   CA       = Σ horas de todas las actividades del operador
     *   peso_i   = horas_actividad_i / CA
     *   aporte_i = efectividad_i × peso_i        (igual que K15, U15... del Excel)
     *   E_total  = Σ aporte_i                    (igual que CB15 del Excel)
     */
    public function weightedEffectiveness(Request $request)
    {
        $query = Activity::with([
            'operator:id,name',
            'process:id,name,base_per_hour'
        ])->where('status', 'CLOSED');
 
        if ($request->filled('date_from'))   $query->whereDate('start_time', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('start_time', '<=', $request->date_to);
        if ($request->filled('operator_id')) $query->where('operator_id', $request->operator_id);
 
        $activities = $query->get();
 
        $result = $activities
            ->groupBy('operator_id')
            ->map(function ($group) {
 
                // CA: total de horas de todas las actividades del operador
                $totalHoras = $group->sum(function ($a) {
                    if (!$a->start_time || !$a->end_time) return 0;
                    return $a->start_time->diffInSeconds($a->end_time) / 3600;
                });
 
                $totalWeighted = 0;
 
                $activitiesDetail = $group->map(function ($a) use ($totalHoras, &$totalWeighted) {
                    $std     = $this->calcStandard($a);
                    $qty     = (float) $a->quantity;
                    $minutes = ($a->start_time && $a->end_time)
                        ? $a->start_time->diffInSeconds($a->end_time) / 60
                        : 0;
                    $horas   = $minutes / 60;
                    $base    = (float) ($a->process->base_per_hour ?? 0);
 
                    // % rendimiento de esta actividad
                    $efectividad = $std > 0 ? ($qty / $std) : 0;
 
                    // peso = horas_actividad / CA  ← igual que Excel
                    $peso   = $totalHoras > 0 ? ($horas / $totalHoras) : 0;
 
                    // aporte ponderado de esta actividad
                    $aporte = $efectividad * $peso;
                    $totalWeighted += $aporte;
 
                    return [
                        'activity_id'   => $a->id,
                        'process'       => $a->process->name ?? '—',
                        'minutes'       => round($minutes, 1),
                        'horas'         => round($horas, 4),
                        'base_per_hour' => $base,
                        'real'          => $qty,
                        'standard'      => round($std, 2),
                        'effectiveness' => round($efectividad * 100, 1),  // % individual
                        'peso'          => round($peso * 100, 2),          // % del tiempo total
                        'weighted'      => round($aporte * 100, 2),        // aporte al ponderado
                    ];
                });
 
                return [
                    'operator_id'            => $group->first()->operator_id,
                    'name'                   => $group->first()->operator->name ?? '—',
                    'total_horas'            => round($totalHoras, 4),
                    'weighted_effectiveness' => round($totalWeighted * 100, 1),
                    'activities'             => $activitiesDetail->values(),
                ];
            })
            ->values();
 
        return response()->json(['data' => $result]);
    }







 
    /**
     * 🔹 REPORTE MANUAL
     */
    public function reportManual(Request $request)
{
    $isGroup = $request->boolean('is_group');

    if ($isGroup) {
        $request->validate([
            'operator_ids'   => 'required|array|min:2',
            'operator_ids.*' => 'exists:users,id',
            'process_id'     => 'required|exists:processes,id',
            'start_time'     => 'required|date',
            'end_time'       => 'required|date|after:start_time',
            'quantity'       => 'required|integer|min:0',
        ]);
    } else {
        $request->validate([
            'operator_id' => 'required|exists:users,id',
            'process_id'  => 'required|exists:processes,id',
            'start_time'  => 'required|date',
            'end_time'    => 'required|date|after:start_time',
            'quantity'    => 'required|integer|min:0',
        ]);
    }

    try {
        $startTime = Carbon::parse($request->start_time);
        $endTime   = Carbon::parse($request->end_time);

        if ($isGroup) {
            // Crear grupo cerrado
            $group = \App\Modules\Prueba\Models\ActivityGroup::create([
                'process_id'    => $request->process_id,
                'supervisor_id' => Auth::id(),
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'quantity'      => $request->quantity,
                'status'        => 'CLOSED',
                'notes'         => $request->notes ?? null,
            ]);

            // Crear registro CLOSED por cada operador
            foreach ($request->operator_ids as $operatorId) {
                DB::table('activities')->insert([
                    'process_id'        => $request->process_id,
                    'operator_id'       => $operatorId,
                    'supervisor_id'     => Auth::id(),
                    'activity_group_id' => $group->id,
                    'start_time'        => $startTime,
                    'end_time'          => $endTime,
                    'quantity'          => $request->quantity,
                    'status'            => 'CLOSED',
                    'is_group_member'   => true,
                    'notes'             => $request->notes ?? null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            return response()->json([
                'message' => 'Reporte grupal registrado correctamente',
                'data'    => $group->load('process')
            ], 201);

        } else {
            $activity = Activity::create([
                'operator_id'   => $request->operator_id,
                'process_id'    => $request->process_id,
                'supervisor_id' => Auth::id(),
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'quantity'      => $request->quantity,
                'status'        => 'CLOSED',
                'notes'         => $request->notes ?? null,
            ]);

            return response()->json([
                'message' => 'Reporte registrado correctamente',
                'data'    => $activity->load(['operator', 'process'])
            ], 201);
        }

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
 
    /**
     * 🔹 AGREGAR / ACTUALIZAR OBSERVACIÓN
     */
    public function addNote(Request $request, $id)
    {
        $request->validate([
            'notes' => 'required|string|max:1000',
        ]);
 
        $activity = Activity::findOrFail($id);
 
        $activity->update(['notes' => $request->notes]);
 
        ActivityLog::create([
            'activity_id' => $activity->id,
            'action'      => 'NOTE',
            'user_id'     => Auth::id() ?? null,
            'timestamp'   => now()
        ]);
 
        return response()->json([
            'message' => 'Observación guardada',
            'data'    => $activity->load(['operator', 'process'])
        ]);
    }
 
    /**
     * 🔹 OBTENER OBSERVACIÓN DE UNA ACTIVIDAD
     */
    public function getNote($id)
    {
        $activity = Activity::findOrFail($id);
 
        return response()->json([
            'activity_id' => $activity->id,
            'notes'       => $activity->notes,
            'updated_at'  => $activity->updated_at,
        ]);
    }


    /**
     * 🔹 UPDATE — Editar un reporte existente
     * Permite modificar: operator_id, process_id, start_time, end_time, quantity, notes
     * Solo disponible para actividades CLOSED (historial)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'operator_id' => 'sometimes|exists:users,id',
            'process_id'  => 'sometimes|exists:processes,id',
            'start_time'  => 'sometimes|date',
            'end_time'    => 'sometimes|date|after:start_time',
            'quantity'    => 'sometimes|integer|min:0',
            'notes'       => 'sometimes|nullable|string|max:1000',
        ]);
 
        $activity = Activity::with(['operator', 'process', 'supervisor'])->findOrFail($id);
 
        // Solo se pueden editar actividades cerradas (historial)
        if ($activity->status !== 'CLOSED') {
            return response()->json([
                'error' => 'Solo se pueden editar actividades cerradas'
            ], 422);
        }
 
        $fields = [];
 
        if ($request->has('operator_id')) $fields['operator_id'] = $request->operator_id;
        if ($request->has('process_id'))  $fields['process_id']  = $request->process_id;
        if ($request->has('quantity'))    $fields['quantity']     = $request->quantity;
        if ($request->has('notes'))       $fields['notes']        = $request->notes;
 
        if ($request->has('start_time')) {
        $fields['start_time'] = Carbon::parse($request->start_time);
        }
        if ($request->has('end_time')) {
        $fields['end_time'] = Carbon::parse($request->end_time);
        }
 
        $activity->update($fields);
 
        // Log de la edición
        ActivityLog::create([
            'activity_id' => $activity->id,
            'action'      => 'EDIT',
            'user_id'     => Auth::id() ?? null,
            'timestamp'   => now(),
        ]);
 
        return response()->json([
            'message' => 'Reporte actualizado correctamente',
            'data'    => $activity->fresh()->load(['operator', 'process', 'supervisor', 'logs.user']),
        ]);
    }
}