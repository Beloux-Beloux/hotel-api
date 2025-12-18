<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id')->index();
            $table->foreignId('room_id')->nullable();
            $table->uuid('staff_id')->nullable();
            $table->string('day_of_week'); // ex: "monday", "tuesday", etc.
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_items');
    }
};
