<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE activities MODIFY COLUMN status ENUM('OPEN', 'STOPPED', 'CLOSED', 'CANCELLED') DEFAULT 'OPEN'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE activities MODIFY COLUMN status ENUM('OPEN', 'CLOSED', 'CANCELLED') DEFAULT 'OPEN'");
    }
};