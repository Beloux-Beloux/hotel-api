<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hotel_id');
            $table->string('user_id')->nullable(); // Pour les notifications spécifiques à un utilisateur
            $table->string('type'); // assignment, priority, issue, validation, reminder, congratulations
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Données supplémentaires
            $table->string('icon')->default('info');
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('sound_enabled')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'user_id']);
            $table->index(['hotel_id', 'read']);
            $table->index(['created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};