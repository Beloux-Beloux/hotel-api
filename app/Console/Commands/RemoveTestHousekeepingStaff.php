<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HousekeepingStaff;
use App\Models\User;

class RemoveTestHousekeepingStaff extends Command
{
    protected $signature = 'housekeeping:remove-test {hotelId=1}';
    protected $description = 'Remove test housekeeping staff created by seeder';

    public function handle()
    {
        $hotelId = $this->argument('hotelId');
        
        // Test staff codes created by seeder
        $testCodes = ['HSK001', 'HSK002', 'HSK003'];
        
        $this->info("Removing test housekeeping staff for hotel $hotelId...");
        
        foreach ($testCodes as $code) {
            $staff = HousekeepingStaff::where('hotel_id', $hotelId)
                ->where('code', $code)
                ->first();
                
            if ($staff) {
                $this->info("Removing $code - {$staff->display_name}");
                
                // Remove staff record
                $staff->delete();
                
                // Optionally remove the user if they have no other roles
                if ($staff->user) {
                    $user = $staff->user;
                    if (!HousekeepingStaff::where('user_id', $user->id)->exists()) {
                        $this->info("  Also removing user account for {$user->name}");
                        $user->delete();
                    }
                }
            }
        }
        
        $this->info("Done!");
        
        return 0;
    }
}