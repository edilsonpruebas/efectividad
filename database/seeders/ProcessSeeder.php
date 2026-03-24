<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Prueba\Models\Process;

class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        $processes = [
            ['name' => 'Doblado',  'is_active' => true],  // 👈 is_active
            ['name' => 'Corte',    'is_active' => true],  // 👈 is_active
            ['name' => 'Envibado', 'is_active' => true],  // 👈 is_active
        ];

        foreach ($processes as $process) {
            Process::create($process);
        }
    }
}