<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega la acción 'EDIT' al enum de activity_logs
     * para registrar quién editó un reporte y cuándo.
     */
    public function up(): void
    {
        // MySQL no permite ALTER ENUM directamente en Blueprint,
        // se hace con una sentencia raw.
        DB::statement("
            ALTER TABLE activity_logs
            MODIFY COLUMN action
            ENUM('START','STOP','STOP_TIMER','CLOSE','CANCEL','NOTE','QUICK_REPORT','RESET','EDIT')
            NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE activity_logs
            MODIFY COLUMN action
            ENUM('START','STOP','STOP_TIMER','CLOSE','CANCEL','NOTE','QUICK_REPORT','RESET')
            NOT NULL
        ");
    }
};