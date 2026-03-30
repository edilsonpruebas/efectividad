<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Prueba\Models\Process;

class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        $processes = [
            ['name' => 'Doblado',  'is_active' => true, 'base_per_hour' => 210],  
            ['name' => 'Corte',    'is_active' => true, 'base_per_hour' => 213.6],  
            ['name' => 'Envibado', 'is_active' => true, 'base_per_hour' => 240],  
        ];

        foreach ($processes as $process) {
            Process::create($process);
        }
    }
}

//doblado de bata cirujano: 210/h BASE
//ENVIVADO: 240/h BASE
//Corte bata cirujano: 213.6/h BASE