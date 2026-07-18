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
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->decimal('base_price', 10, 2);
            $table->integer('max_occupancy');
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->timestamps();
            
            $table->unique(['hotel_id', 'code']);
            $table->index('hotel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};