<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use App\Models\Hotel;
use App\Models\HousekeepingStaff;

class ListHousekeepingUsers extends Command
{
    protected $signature = 'list:housekeeping-users {hotelId=1}';
    protected $description = 'List all users with housekeeper role in a hotel';

    public function handle()
    {
        $hotelId = $this->argument('hotelId');
        $hotel = Hotel::find($hotelId);
        
        if (!$hotel) {
            $this->error("Hotel not found");
            return 1;
        }
        
        $this->info("=== Hotel: {$hotel->name} (ID: {$hotel->id}) ===\n");
        
        // Get housekeeper role
        $housekeeperRole = Role::where('name', 'housekeeper')->first();
        
        if (!$housekeeperRole) {
            $this->error("Housekeeper role not found!");
            return 1;
        }
        
        $this->info("Housekeeper Role ID: {$housekeeperRole->id}");
        
        // Get all users in this hotel
        $this->info("\n1. ALL USERS IN HOTEL:");
        $hotelUsers = User::whereHas('hotels', function($q) use ($hotelId) {
            $q->where('hotels.id', $hotelId);
        })->get();
        
        foreach ($hotelUsers as $user) {
            $roles = $user->roles->pluck('name')->toArray();
            $this->info("   - {$user->name} (ID: {$user->id}) - Roles: " . implode(', ', $roles));
        }
        
        // Get users with housekeeper role in this hotel
        $this->info("\n2. USERS WITH HOUSEKEEPER ROLE IN THIS HOTEL:");
        $housekeepers = User::whereHas('roles', function($q) use ($housekeeperRole) {
                $q->where('role_id', $housekeeperRole->id);
            })
            ->whereHas('hotels', function($q) use ($hotelId) {
                $q->where('hotels.id', $hotelId);
            })
            ->get();
            
        if ($housekeepers->isEmpty()) {
            $this->warn("   No users with housekeeper role found in this hotel");
        } else {
            foreach ($housekeepers as $user) {
                $this->info("   - {$user->name} (ID: {$user->id}, Email: {$user->email})");
                
                // Check if housekeeping_staff record exists
                $staff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)
                    ->where('hotel_id', $hotelId)
                    ->where('user_id', $user->id)
                    ->first();
                    
                if ($staff) {
                    $this->info("     * Staff record exists: Code={$staff->code}, Active=" . ($staff->active ? 'Yes' : 'No'));
                } else {
                    $this->warn("     * No housekeeping_staff record");
                }
            }
        }
        
        // Show all housekeeping_staff records for this hotel
        $this->info("\n3. ALL HOUSEKEEPING_STAFF RECORDS FOR THIS HOTEL:");
        $allStaff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)
            ->where('hotel_id', $hotelId)
            ->get();
            
        foreach ($allStaff as $staff) {
            $userName = $staff->user ? $staff->user->name : 'No user';
            $this->info("   - {$staff->code}: {$userName} (User ID: {$staff->user_id}, Active: " . ($staff->active ? 'Yes' : 'No') . ")");
        }
        
        return 0;
    }
}