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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->json('address')->nullable();
            $table->json('contact_info')->nullable();
            $table->json('settings')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone', 50)->default('Europe/Paris');
            $table->boolean('is_active')->default(true);
            $table->string('subscription_plan', 50)->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
