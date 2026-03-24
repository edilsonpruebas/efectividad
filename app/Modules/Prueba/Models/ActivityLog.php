<?php

namespace App\Modules\Prueba\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'action',
        'user_id',
        'timestamp',
        'data'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'data' => 'array',
    ];

    // 🔗 RELACIONES

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}