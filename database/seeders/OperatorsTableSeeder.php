<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Modules\Prueba\Models\User;

class OperatorsTableSeeder extends Seeder
{
    public function run(): void
    {
        
        // SUPERVISOR (sí hace login)
        User::updateOrCreate(['email' => 'CONTADOR1@CMV.com'], [
            'name'      => 'CONTADOR1',
            'password'  => Hash::make('M4rzo5'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'CONTADOR2@CMV.com'], [
            'name'      => 'CONTADOR2',
            'password'  => Hash::make('L8nter2'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'CONTADOR3@CMV.com'], [
            'name'      => 'CONTADOR3',
            'password'  => Hash::make('R0ble7'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);

         User::updateOrCreate(['email' => 'CONTADOR4@CMV.com'], [
            'name'      => 'CONTADOR4',
            'password'  => Hash::make('S0lar9'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);

         User::updateOrCreate(['email' => 'CONTADOR5@CMV.com'], [
            'name'      => 'CONTADOR5',
            'password'  => Hash::make('N3xus4'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);

         User::updateOrCreate(['email' => 'CONTADOR6@CMV.com'], [
            'name'      => 'CONTADOR6',
            'password'  => Hash::make('V1ento6'),
            'role'      => 'SUPERVISOR',
            'is_active' => true,
        ]);



        // ADMIN (sí hace login)
            User::updateOrCreate(['email' => 'admin@CMV.com'], [
                'name'      => 'Administrador',
                'password'  => Hash::make('password'),
                'role'      => 'ADMIN',
                'is_active' => true,
            ]);

            User::updateOrCreate(['email' => 'RRHH@CMV.com'], [
                'name'      => 'RRHH',        // ← cambiado
                'password'  => Hash::make('Rr9h2'),
                'role'      => 'ADMIN',
                'is_active' => true,
            ]);

            User::updateOrCreate(['email' => 'FABRICA@CMV.com'], [
                'name'      => 'FABRICA',     // ← cambiado
                'password'  => Hash::make('F4bri2'),
                'role'      => 'ADMIN',
                'is_active' => true,
            ]);

            User::updateOrCreate(['email' => 'OPERACIONES@CMV.com'], [
                'name'      => 'OPERACIONES', // ← cambiado
                'password'  => Hash::make('0pera3'),
                'role'      => 'ADMIN',
                'is_active' => true,
            ]);


            User::updateOrCreate(['email' => 'VP@CMV.com'], [
                'name'      => 'VP', // ← cambiado
                'password'  => Hash::make('Vp7x1'),
                'role'      => 'ADMIN',
                'is_active' => true,
            ]);
            
    }
}