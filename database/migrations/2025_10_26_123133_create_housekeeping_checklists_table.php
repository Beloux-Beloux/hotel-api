<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('housekeeping_checklists', function (Blueprint $table) {
            $table->id();
            $table->uuid('assignment_id')->index();
            $table->json('items'); // Stocke les items complets (label, status, etc.)
            $table->integer('progress')->default(0); // pourcentage de progression
            $table->integer('estimated_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_checklists');
    }
};
