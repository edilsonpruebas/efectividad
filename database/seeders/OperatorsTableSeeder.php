<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Modules\Prueba\Models\User;

class OperatorsTableSeeder extends Seeder  // 👈 nombre debe coincidir con el archivo
{
    public function run(): void
    {
        // OPERADORES
        User::create([
            'name'     => 'Juan Perez',
            'email'    => 'juan@prueba.com',
            'password' => Hash::make('password'),
            'role'     => 'OPERADOR',
        ]);

        User::create([
            'name'     => 'Pedro Garcia',
            'email'    => 'pedro@prueba.com',
            'password' => Hash::make('password'),
            'role'     => 'OPERADOR',
        ]);

        User::create([
            'name'     => 'Maria Lopez',
            'email'    => 'maria@prueba.com',
            'password' => Hash::make('password'),
            'role'     => 'OPERADOR',
        ]);

        // SUPERVISORES
        User::create([
            'name'     => 'Carlos Mendez',
            'email'    => 'carlos@prueba.com',
            'password' => Hash::make('password'),
            'role'     => 'SUPERVISOR',
        ]);

        // ADMIN
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@prueba.com',
            'password' => Hash::make('password'),
            'role'     => 'ADMIN',
        ]);
    }
}