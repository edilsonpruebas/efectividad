<?php

namespace App\Modules\Prueba\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
        'operator_id',
        'supervisor_id',
        'start_time',
        'end_time',
        'quantity',
        'status',
        'notes'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    // 🔗 RELACIONES

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function logs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // 🔍 SCOPES

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['OPEN', 'STOPPED']);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'CLOSED');
    }

    // 🧠 LÓGICA

    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }

    public function isStopped(): bool
    {
        return $this->status === 'STOPPED';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['OPEN', 'STOPPED']);
    }

    public function durationInMinutes()
    {
        if (!$this->end_time) return null;
        return $this->start_time->diffInMinutes($this->end_time);
    }

    // ⏱️ STOP TIMER — Fase 2: captura end_time, status = STOPPED
    public function stopTimer(): static
    {
        if (!$this->isOpen()) {
            throw new \Exception('Solo actividades abiertas pueden detenerse');
        }

        $this->update([
            'end_time' => now(),
            'status'   => 'STOPPED',
        ]);

        try {
            ActivityLog::create([
                'activity_id' => $this->id,
                'action'      => 'STOP_TIMER',
                'user_id'     => Auth::id() ?? null,
                'timestamp'   => now(),
            ]);
        } catch (\Exception $e) {}

        return $this;
    }

    // 📊 SUBMIT REPORT — Fase 3: guarda reporte, resetea a OPEN para nuevo ciclo
    public function submitReport($quantity, $notes = null): static
    {
        if (!$this->isStopped()) {
            throw new \Exception('La actividad debe estar detenida antes de enviar el reporte');
        }

        // Guardar el reporte en el historial como actividad CLOSED independiente
        $report = static::create([
            'process_id'    => $this->process_id,
            'operator_id'   => $this->operator_id,
            'supervisor_id' => $this->supervisor_id,
            'start_time'    => $this->start_time,
            'end_time'      => $this->end_time,
            'quantity'      => $quantity,
            'status'        => 'CLOSED',
            'notes'         => $notes,
        ]);

        try {
            ActivityLog::create([
                'activity_id' => $report->id,
                'action'      => 'CLOSE',
                'user_id'     => Auth::id() ?? null,
                'timestamp'   => now(),
            ]);
        } catch (\Exception $e) {}

        // Resetear la actividad actual a OPEN con nuevo start_time
        $this->update([
            'start_time' => now(),
            'end_time'   => null,
            'quantity'   => null,
            'notes'      => null,
            'status'     => 'OPEN',
        ]);

        try {
            ActivityLog::create([
                'activity_id' => $this->id,
                'action'      => 'RESET',
                'user_id'     => Auth::id() ?? null,
                'timestamp'   => now(),
            ]);
        } catch (\Exception $e) {}

        return $this;
    }

    // 🚫 CANCEL
    public function cancel(): static
    {
        if (!$this->isActive()) {
            throw new \Exception('Solo actividades activas pueden cancelarse');
        }

        $this->update([
            'status'   => 'CANCELLED',
            'end_time' => $this->end_time ?? now(),
        ]);

        try {
            ActivityLog::create([
                'activity_id' => $this->id,
                'action'      => 'CANCEL',
                'user_id'     => Auth::id() ?? null,
                'timestamp'   => now(),
            ]);
        } catch (\Exception $e) {}

        return $this;
    }

    // 🔒 VALIDACIÓN GLOBAL
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            // Solo bloquear si no es un reporte CLOSED (los reportes se crean directamente como CLOSED)
            if (isset($activity->status) && $activity->status === 'CLOSED') {
                return;
            }

            $exists = self::where('operator_id', $activity->operator_id)
                ->whereIn('status', ['OPEN', 'STOPPED'])
                ->exists();

            if ($exists) {
                throw new \Exception('El operador ya tiene una actividad activa');
            }
        });
    }
}