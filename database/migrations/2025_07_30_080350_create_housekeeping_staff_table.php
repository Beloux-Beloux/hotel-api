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
        Schema::create('housekeeping_staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('code', 20)->unique();
            $table->json('floor_preferences')->nullable();
            $table->integer('max_rooms_per_day')->default(15);
            $table->boolean('active')->default(true);
            $table->json('skills')->nullable();
            $table->timestamps();
            
            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['hotel_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_staff');
    }
};
