<?php

namespace App\Modules\Prueba\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',   // ← agregado
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',  // ← agregado
    ];

    // 🔗 RELACIONES

    public function activitiesAsOperator()
    {
        return $this->hasMany(Activity::class, 'operator_id');
    }

    public function activitiesAsSupervisor()
    {
        return $this->hasMany(Activity::class, 'supervisor_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    // 🔍 SCOPES

    public function scopeOperators($query)
    {
        return $query->where('role', 'OPERADOR');
    }

    public function scopeActiveOperators($query)
    {
        return $query->where('role', 'OPERADOR')->where('is_active', true); // ← agregado
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', 'SUPERVISOR');
    }
}