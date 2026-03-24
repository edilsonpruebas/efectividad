<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();

            $table->enum('action', ['START', 'STOP', 'EDIT', 'CANCEL']);

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('timestamp');

            $table->json('data')->nullable(); // snapshot opcional

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};