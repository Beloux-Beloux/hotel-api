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
        Schema::create('cleaning_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('room_type_id')->nullable();
            $table->string('name');
            $table->json('items')->nullable();
            $table->integer('estimated_minutes')->default(30);
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
            $table->foreign('room_type_id')->references('id')->on('room_types')->onDelete('set null');
            
            $table->index(['hotel_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cleaning_checklists');
    }
};
