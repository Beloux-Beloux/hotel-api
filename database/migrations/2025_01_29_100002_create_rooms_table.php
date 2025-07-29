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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('number', 10);
            $table->foreignId('room_type_id')->constrained();
            $table->integer('floor');
            $table->enum('status', [
                'libre_propre',
                'libre_sale',
                'occupee_propre',
                'occupee_sale',
                'en_nettoyage',
                'hors_service',
                'reservee'
            ])->default('libre_propre');
            $table->json('features')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'number']);
            $table->index('hotel_id');
            $table->index('status');
            $table->index('floor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};