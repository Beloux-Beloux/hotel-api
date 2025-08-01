<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HousekeepingStaff;
use App\Models\User;

class DebugHousekeepingStaff extends Command
{
    protected $signature = 'debug:housekeeping-staff {userId}';
    protected $description = 'Debug housekeeping staff visibility';

    public function handle()
    {
        $userId = $this->argument('userId');
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User not found");
            return 1;
        }
        
        $this->info("User: {$user->name} (ID: {$user->id})");
        $this->info("Current Hotel ID: {$user->current_hotel_id}");
        $this->info("Is Super Admin: " . ($user->is_super_admin ? 'Yes' : 'No'));
        
        // Set auth user for testing
        auth()->login($user);
        
        // Test 1: Raw query without scope
        $this->info("\n1. ALL STAFF (without scope):");
        $allStaff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)->get();
        foreach ($allStaff as $staff) {
            $this->info("   - {$staff->display_name} (Hotel: {$staff->hotel_id}, Active: " . ($staff->active ? 'Yes' : 'No') . ")");
        }
        
        // Test 2: With scope (normal query)
        $this->info("\n2. STAFF WITH SCOPE (normal query):");
        $scopedStaff = HousekeepingStaff::all();
        $this->info("   Found: {$scopedStaff->count()} staff members");
        foreach ($scopedStaff as $staff) {
            $this->info("   - {$staff->display_name}");
        }
        
        // Test 3: Direct hotel filter
        if ($user->current_hotel_id) {
            $this->info("\n3. STAFF FOR HOTEL {$user->current_hotel_id}:");
            $hotelStaff = HousekeepingStaff::withoutGlobalScope(\App\Scopes\HotelScope::class)
                ->where('hotel_id', $user->current_hotel_id)
                ->get();
            $this->info("   Found: {$hotelStaff->count()} staff members");
            foreach ($hotelStaff as $staff) {
                $this->info("   - {$staff->display_name} (Active: " . ($staff->active ? 'Yes' : 'No') . ")");
            }
        }
        
        return 0;
    }
}