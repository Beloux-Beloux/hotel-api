<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_timers', function (Blueprint $table) {
            $table->id();
            $table->string('task_id', 36)->unique(); // UUID
            $table->integer('elapsed_seconds')->default(0);
            $table->enum('status', ['running', 'stopped'])->default('stopped');
            $table->timestamp('start_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_timers');
    }
};
