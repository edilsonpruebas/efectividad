<?php
// App/Modules/Prueba/Models/ActivityGroup.php

namespace App\Modules\Prueba\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityGroup extends Model
{
    protected $fillable = [
        'process_id', 'supervisor_id',
        'start_time', 'end_time',
        'quantity', 'status', 'notes'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    // ── RELACIONES ──────────────────────────────────────────────────────

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Las actividades individuales de cada operador en el grupo
     */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'activity_group_id');
    }

    /**
     * Acceso directo a los operadores vía activities
     */
    public function operators()
    {
        return $this->hasManyThrough(
            User::class,
            Activity::class,
            'activity_group_id', // FK en activities
            'id',                // PK en users
            'id',                // PK en activity_groups
            'operator_id'        // FK en activities
        );
    }

    // ── ESTADO ──────────────────────────────────────────────────────────

    public function isOpen(): bool     { return $this->status === 'OPEN'; }
    public function isStopped(): bool  { return $this->status === 'STOPPED'; }
    public function isActive(): bool   { return in_array($this->status, ['OPEN', 'STOPPED']); }

    // ── ACCIONES ────────────────────────────────────────────────────────

    /**
     * Detiene el timer del grupo y de todas sus actividades individuales
     */
    public function stopTimer(): static
    {
        if (!$this->isOpen()) {
            throw new \Exception('Solo grupos abiertos pueden detenerse');
        }

        $now = now();

        $this->update(['end_time' => $now, 'status' => 'STOPPED']);

        // Detener todas las actividades miembro
        $this->activities()
             ->where('status', 'OPEN')
             ->update(['end_time' => $now, 'status' => 'STOPPED']);

        return $this;
    }

    /**
     * Cierra el grupo: guarda la cantidad ÚNICA y crea registros CLOSED
     * para cada operador con quantity = cantidad_total (no dividida)
     * pero marcados como parte de un grupo (evita doble conteo en métricas)
     */
    public function submitReport(int $quantity, ?string $notes = null): static
{
    if (!$this->isStopped()) {
        throw new \Exception('El grupo debe estar detenido antes de enviar el reporte');
    }

    // ── 1. Guardar registro histórico del grupo ───────────────────────
    // NO cerrar $this, crear un registro CLOSED independiente
    $closedGroup = static::create([
        'process_id'    => $this->process_id,
        'supervisor_id' => $this->supervisor_id,
        'start_time'    => $this->start_time,
        'end_time'      => $this->end_time,
        'quantity'      => $quantity,
        'status'        => 'CLOSED',
        'notes'         => $notes,
    ]);

    // ── 2. Crear actividad CLOSED por cada operador (historial) ───────
    foreach ($this->activities()->whereIn('status', ['OPEN', 'STOPPED'])->get() as $activity) {

        // Crear registro histórico CLOSED — saltar el boot con newModelInstance
        // usando insert directo para evitar el check de actividad activa
        DB::table('activities')->insert([
            'process_id'        => $this->process_id,
            'operator_id'       => $activity->operator_id,
            'supervisor_id'     => $this->supervisor_id,
            'activity_group_id' => $closedGroup->id,
            'start_time'        => $this->start_time,
            'end_time'          => $this->end_time,
            'quantity'          => $quantity,
            'status'            => 'CLOSED',
            'is_group_member'   => true,
            'notes'             => $notes,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ── 3. Resetear actividades trackers a OPEN (nuevo ciclo) ─────────
    $this->activities()->whereIn('status', ['OPEN', 'STOPPED'])->update([
        'start_time' => now(),
        'end_time'   => null,
        'quantity'   => null,
        'status'     => 'OPEN',
    ]);

    // ── 4. Resetear el grupo tracker a OPEN (nuevo ciclo) ─────────────
    $this->update([
        'start_time' => now(),
        'end_time'   => null,
        'quantity'   => null,
        'notes'      => null,
        'status'     => 'OPEN',
    ]);

    return $this;
}

    /**
     * Cancela el grupo y sus actividades
     */
    public function cancel(): static
    {
        $this->update(['status' => 'CANCELLED', 'end_time' => $this->end_time ?? now()]);
        $this->activities()->whereIn('status', ['OPEN', 'STOPPED'])
             ->update(['status' => 'CANCELLED', 'end_time' => now()]);
        return $this;
    }
}