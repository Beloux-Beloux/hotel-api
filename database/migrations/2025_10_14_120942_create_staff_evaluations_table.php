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
         Schema::create('staff_evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('staff_id');
            $table->foreign('staff_id')->references('id')->on('housekeeping_staff')->onDelete('cascade');

            $table->date('date');
            $table->integer('score')->default(0);
            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_evaluations');
    }
};
