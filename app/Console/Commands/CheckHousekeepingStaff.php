<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\HousekeepingStaff;
use App\Models\Role;

class CheckHousekeepingStaff extends Command
{
    protected $signature = 'check:housekeeping {hotelId=1}';
    protected $description = 'Check housekeeping staff data';

    public function handle()
    {
        $hotelId = $this->argument('hotelId');
        
        // Check users with housekeeper role
        $housekeepingRole = Role::where('name', 'housekeeper')->first();
        
        if (!$housekeepingRole) {
            $this->error("Housekeeper role not found!");
            return 1;
        }
        
        $housekeepingUsers = User::whereHas('roles', function($q) use ($housekeepingRole) {
            $q->where('role_id', $housekeepingRole->id);
        })->get();
        
        $this->info("Users with housekeeper role: {$housekeepingUsers->count()}");
        foreach ($housekeepingUsers as $user) {
            $this->info("  - {$user->name} ({$user->email})");
        }
        
        // Check housekeeping_staff records
        $allStaff = HousekeepingStaff::all();
        $hotelStaff = HousekeepingStaff::where('hotel_id', $hotelId)->get();
        
        $this->info("\nHousekeeping staff records:");
        $this->info("  Total: {$allStaff->count()}");
        $this->info("  For hotel $hotelId: {$hotelStaff->count()}");
        
        foreach ($hotelStaff as $staff) {
            $this->info("\n  Staff: {$staff->display_name}");
            $this->info("    - ID: {$staff->id}");
            $this->info("    - Code: {$staff->code}");
            $this->info("    - User ID: {$staff->user_id}");
            $this->info("    - Active: " . ($staff->active ? 'Yes' : 'No'));
            $this->info("    - Max rooms: {$staff->max_rooms_per_day}");
        }
        
        // Check for housekeeping users without staff records
        $this->info("\nChecking for missing staff records...");
        foreach ($housekeepingUsers as $user) {
            $staff = HousekeepingStaff::where('user_id', $user->id)->first();
            if (!$staff) {
                $this->warn("  User {$user->name} has housekeeper role but no staff record!");
            }
        }
        
        return 0;
    }
}