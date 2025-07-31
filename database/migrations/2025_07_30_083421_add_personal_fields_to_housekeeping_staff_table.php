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
        Schema::table('housekeeping_staff', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('code');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('last_name');
            $table->string('email')->nullable()->after('phone');
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('email');
            $table->date('hire_date')->nullable()->after('hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('housekeeping_staff', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name', 
                'phone',
                'email',
                'hourly_rate',
                'hire_date'
            ]);
        });
    }
};
