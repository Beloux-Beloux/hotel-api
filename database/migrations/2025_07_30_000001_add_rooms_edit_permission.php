<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the new permission
        $permission = Permission::create([
            'name' => 'rooms.edit',
            'display_name' => 'Gérer les chambres et types',
            'module' => 'hebergement'
        ]);

        // Assign to admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && !$adminRole->permissions()->where('name', 'rooms.edit')->exists()) {
            $adminRole->permissions()->attach($permission);
        }

        // Assign to manager role
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole && !$managerRole->permissions()->where('name', 'rooms.edit')->exists()) {
            $managerRole->permissions()->attach($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permission from roles
        $permission = Permission::where('name', 'rooms.edit')->first();
        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};