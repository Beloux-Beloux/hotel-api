<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_timer_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task_id', 36);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->integer('elapsed_seconds');
            $table->timestamps();

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_timer_logs');
    }
};
