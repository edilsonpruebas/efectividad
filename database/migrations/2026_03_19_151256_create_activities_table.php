<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();

            // FASE 1 (START)
            $table->timestamp('start_time');

            // FASE 2 (STOP)
            $table->timestamp('end_time')->nullable();
            $table->integer('quantity')->nullable();

            $table->enum('status', ['OPEN', 'CLOSED', 'CANCELLED'])->default('OPEN');

            $table->text('notes')->nullable();

            $table->timestamps();

            // 🔥 Índices importantes
            $table->index(['operator_id', 'status']);
            $table->index('start_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};