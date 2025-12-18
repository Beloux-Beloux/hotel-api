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
        Schema::create('housekeeping_settings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('hotel_id');
        $table->json('default_cleaning_times')->nullable();
        $table->integer('max_rooms_per_staff')->default(5);
        $table->json('working_hours')->nullable();
        $table->boolean('notifications_enabled')->default(true);
        $table->json('alert_thresholds')->nullable();
        $table->timestamps();

        $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_settings');
    }
};
