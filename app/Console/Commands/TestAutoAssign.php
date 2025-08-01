<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Room;
use App\Models\HousekeepingStaff;
use App\Models\RoomAssignment;
use App\Services\RoomAssignmentService;
use Carbon\Carbon;

class TestAutoAssign extends Command
{
    protected $signature = 'test:auto-assign {hotelId=1}';
    protected $description = 'Test auto-assignment functionality';

    public function handle(RoomAssignmentService $service)
    {
        $hotelId = $this->argument('hotelId');
        $date = Carbon::today();
        
        $this->info("=== Testing Auto-Assignment for Hotel $hotelId on {$date->format('Y-m-d')} ===");
        
        // 1. Check dirty rooms
        $dirtyRooms = Room::where('hotel_id', $hotelId)
            ->whereIn('status', ['libre_sale', 'occupee_sale'])
            ->get();
            
        $this->info("\n1. Dirty Rooms:");
        $this->info("   Found {$dirtyRooms->count()} dirty rooms");
        foreach ($dirtyRooms as $room) {
            $this->info("   - Room {$room->number} (Floor {$room->floor}): {$room->status}");
        }
        
        // 2. Check existing assignments
        $existingAssignments = RoomAssignment::where('hotel_id', $hotelId)
            ->where('assigned_date', $date)
            ->whereIn('room_id', $dirtyRooms->pluck('id'))
            ->get();
            
        $this->info("\n2. Existing Assignments for Today:");
        $this->info("   Found {$existingAssignments->count()} existing assignments");
        foreach ($existingAssignments as $assignment) {
            $this->info("   - Room {$assignment->room->number}: {$assignment->status}");
        }
        
        // 3. Check available staff
        $allStaff = HousekeepingStaff::where('hotel_id', $hotelId)->get();
        $activeStaff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('active', true)
            ->get();
            
        $this->info("\n3. Housekeeping Staff:");
        $this->info("   Total staff: {$allStaff->count()}");
        $this->info("   Active staff: {$activeStaff->count()}");
        
        foreach ($activeStaff as $staff) {
            $todayAssignments = RoomAssignment::where('hotel_id', $hotelId)
                ->where('staff_id', $staff->id)
                ->where('assigned_date', $date)
                ->count();
                
            $this->info("   - {$staff->display_name} (ID: {$staff->id}):");
            $this->info("     * Max rooms: {$staff->max_rooms_per_day}");
            $this->info("     * Today's assignments: $todayAssignments");
            $this->info("     * Can accept more: " . ($todayAssignments < $staff->max_rooms_per_day ? 'Yes' : 'No'));
        }
        
        // 4. Rooms that need assignment
        $roomsNeedingAssignment = $dirtyRooms->filter(function ($room) use ($existingAssignments) {
            return !$existingAssignments->contains('room_id', $room->id);
        });
        
        $this->info("\n4. Rooms Needing Assignment:");
        $this->info("   {$roomsNeedingAssignment->count()} rooms need assignment");
        
        // 5. Try auto-assignment
        $this->info("\n5. Running Auto-Assignment...");
        
        try {
            $result = $service->autoAssign($hotelId, $date);
            
            $this->info("\nResult:");
            $this->info("   Assigned: {$result['assigned']} rooms");
            $this->info("   Unassigned: {$result['unassigned']} rooms");
            
            if (isset($result['staff_assignments'])) {
                $this->info("\nStaff Assignments:");
                foreach ($result['staff_assignments'] as $assignment) {
                    $this->info("   - {$assignment['staff']}: {$assignment['assigned']} rooms on floors " . implode(', ', $assignment['floors']));
                }
            }
        } catch (\Exception $e) {
            $this->error("\nError during auto-assignment: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
        
        return 0;
    }
}