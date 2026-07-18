<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;
use App\Models\HousekeepingStaff;
use App\Models\RoomAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckAssignmentStatus extends Command
{
    protected $signature = 'check:assignment-status {hotelId=1} {--date=}';
    protected $description = 'Check assignment status for debugging';

    public function handle()
    {
        $hotelId = $this->argument('hotelId');
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        
        $this->info("=== Assignment Status for Hotel $hotelId on {$date->format('Y-m-d')} ===\n");
        
        // 1. Check all staff
        $this->info("1. ALL STAFF:");
        $allStaff = HousekeepingStaff::where('hotel_id', $hotelId)->get();
        
        if ($allStaff->isEmpty()) {
            $this->warn("   No staff found for hotel $hotelId");
        } else {
            foreach ($allStaff as $staff) {
                $status = $staff->active ? 'Active' : 'Inactive';
                $this->info("   - {$staff->display_name} (ID: {$staff->id})");
                $this->info("     * Status: $status");
                $this->info("     * Code: {$staff->code}");
                $this->info("     * Max rooms: {$staff->max_rooms_per_day}");
                
                // Check assignments for this date
                $assignments = RoomAssignment::where('hotel_id', $hotelId)
                    ->where('staff_id', $staff->id)
                    ->where('assigned_date', $date)
                    ->whereNotIn('status', ['cancelled'])
                    ->count();
                    
                $this->info("     * Assignments today: $assignments");
                $this->info("     * Available capacity: " . ($staff->max_rooms_per_day - $assignments));
            }
        }
        
        // 2. Check dirty rooms
        $this->info("\n2. DIRTY ROOMS:");
        $dirtyRooms = Room::where('hotel_id', $hotelId)
            ->whereIn('status', ['libre_sale', 'occupee_sale'])
            ->get();
            
        if ($dirtyRooms->isEmpty()) {
            $this->warn("   No dirty rooms found");
        } else {
            $this->info("   Found {$dirtyRooms->count()} dirty rooms:");
            foreach ($dirtyRooms as $room) {
                $this->info("   - Room {$room->number} (Floor {$room->floor}): {$room->status}");
                
                // Check if already assigned
                $assignment = RoomAssignment::where('hotel_id', $hotelId)
                    ->where('room_id', $room->id)
                    ->where('assigned_date', $date)
                    ->whereNotIn('status', ['cancelled'])
                    ->first();
                    
                if ($assignment) {
                    $this->warn("     * Already assigned to: {$assignment->staff->display_name}");
                }
            }
        }
        
        // 3. Check available staff using the service logic
        $this->info("\n3. AVAILABLE STAFF (using service logic):");
        
        $activeStaff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('active', true)
            ->get();
            
        $availableCount = 0;
        foreach ($activeStaff as $staff) {
            $assignedCount = DB::table('room_assignments')
                ->where('assigned_date', $date)
                ->where('hotel_id', $hotelId)
                ->where('staff_id', $staff->id)
                ->whereNotIn('status', ['cancelled'])
                ->count();
                
            if ($assignedCount < $staff->max_rooms_per_day) {
                $availableCount++;
                $this->info("   - {$staff->display_name}: {$assignedCount}/{$staff->max_rooms_per_day} assigned");
            }
        }
        
        if ($availableCount === 0) {
            $this->warn("   No available staff!");
        }
        
        // 4. Summary
        $this->info("\n4. SUMMARY:");
        $this->info("   - Total staff: {$allStaff->count()}");
        $this->info("   - Active staff: {$activeStaff->count()}");
        $this->info("   - Available staff: $availableCount");
        $this->info("   - Dirty rooms: {$dirtyRooms->count()}");
        $this->info("   - Rooms needing assignment: " . 
            $dirtyRooms->filter(function($room) use ($hotelId, $date) {
                return !RoomAssignment::where('hotel_id', $hotelId)
                    ->where('room_id', $room->id)
                    ->where('assigned_date', $date)
                    ->whereNotIn('status', ['cancelled'])
                    ->exists();
            })->count()
        );
        
        return 0;
    }
}