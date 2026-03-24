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
        'end_time' => 'datetime',
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
        return $query->where('status', 'OPEN');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'CLOSED');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', now()->toDateString());
    }

    // 🧠 LÓGICA DE NEGOCIO

    public function isOpen()
    {
        return $this->status === 'OPEN';
    }

    public function durationInMinutes()
    {
        if (!$this->end_time) {
            return null;
        }

        return $this->start_time->diffInMinutes($this->end_time);
    }

    // 🔥 MÉTODO PARA CERRAR ACTIVIDAD

    public function close($quantity)
    {
        if (!$this->isOpen()) {
            throw new \Exception('La actividad ya está cerrada');
        }

        $this->update([
            'end_time' => now(),
            'quantity' => $quantity,
            'status' => 'CLOSED'
        ]);

        ActivityLog::create([
            'activity_id' => $this->id,
            'action' => 'STOP',
            'user_id' => Auth::id(),
            'timestamp' => now()
        ]);

        return $this;
    }

    public static function boot()
{
    parent::boot();

    static::creating(function ($activity) {
        $exists = self::where('operator_id', $activity->operator_id)
            ->where('status', 'OPEN')
            ->exists();

        if ($exists) {
            throw new \Exception('El operador ya tiene una actividad abierta');
        }
    });
}
}