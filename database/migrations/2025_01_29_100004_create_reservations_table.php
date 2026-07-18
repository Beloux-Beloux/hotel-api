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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('booking_number', 20);
            $table->foreignId('guest_id')->constrained();
            $table->foreignId('room_id')->nullable()->constrained();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->enum('status', [
                'confirmee',
                'en_cours',
                'terminee',
                'annulee',
                'no_show'
            ])->default('confirmee');
            $table->decimal('room_rate', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->text('special_requests')->nullable();
            $table->string('source', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['hotel_id', 'booking_number']);
            $table->index('hotel_id');
            $table->index(['check_in_date', 'check_out_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};