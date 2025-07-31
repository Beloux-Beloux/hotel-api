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
        Schema::create('room_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('room_id');
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('changed_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('hotel_id')->references('id')->on('hotels')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users');
            
            $table->index(['room_id', 'changed_at']);
            $table->index(['hotel_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_status_history');
    }
};
