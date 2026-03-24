<?php

namespace App\Modules\Prueba\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Process extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // 🔗 RELACIONES

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    // 🔍 SCOPES

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}