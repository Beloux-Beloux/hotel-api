<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Liens avec RoomAssignment
            $table->unsignedBigInteger('room_id');
            $table->uuid('staff_id');
            $table->date('assigned_date');

            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])
                  ->default('pending');

            $table->integer('duration_minutes')->nullable(); // durée estimée ou calculée
            $table->text('notes')->nullable();

            $table->timestamps();

            // Clés étrangères
            $table->foreign('staff_id')->references('id')->on('housekeeping_staff')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();

            // Index pour recherche rapide par staff + date
            $table->index(['staff_id', 'assigned_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_tasks');
    }
};
