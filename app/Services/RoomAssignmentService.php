<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\HousekeepingStaff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomAssignmentService
{
    protected WebSocketService $websocket;

    public function __construct(WebSocketService $websocket)
    {
        $this->websocket = $websocket;
    }

    /**
     * Auto-assign rooms to housekeeping staff for a given date.
     */
    public function autoAssign($hotelId, Carbon $date)
    {
        // Get rooms that need cleaning
        $roomsToClean = $this->getRoomsToClean($hotelId);
        \Log::info('Rooms to clean', ['hotel_id' => $hotelId, 'count' => $roomsToClean->count(), 'rooms' => $roomsToClean->pluck('number')]);

        // Get available staff
        $availableStaff = $this->getAvailableStaff($hotelId, $date);
        \Log::info('Available staff', ['hotel_id' => $hotelId, 'date' => $date->format('Y-m-d'), 'count' => $availableStaff->count(), 'staff' => $availableStaff->pluck('display_name')]);

        if ($availableStaff->isEmpty() || $roomsToClean->isEmpty()) {
            \Log::warning('Auto-assign aborted', ['rooms' => $roomsToClean->count(), 'staff' => $availableStaff->count()]);
            return [
                'assigned' => 0,
                'unassigned' => $roomsToClean->count()
            ];
        }

        // Group rooms by floor
        $roomsByFloor = $roomsToClean->groupBy('floor');

        // Calculate workload per staff
        $totalRooms = $roomsToClean->count();
        $staffCount = $availableStaff->count();
        $baseRoomsPerStaff = intval($totalRooms / $staffCount);
        $remainingRooms = $totalRooms % $staffCount;

        // Initialize staff workload
        $staffWorkload = [];
        foreach ($availableStaff as $staff) {
            $staffWorkload[$staff->id] = [
                'staff' => $staff,
                'assigned' => 0,
                'max' => $baseRoomsPerStaff + ($remainingRooms-- > 0 ? 1 : 0),
                'floors' => []
            ];
        }

        DB::beginTransaction();
        try {
            $assignedCount = 0;

            // Assign rooms by floor preference
            foreach ($roomsByFloor as $floor => $rooms) {
                // Find staff with preference for this floor
                $preferredStaff = $availableStaff->filter(function ($staff) use ($floor) {
                    return in_array($floor, $staff->floor_preferences ?? []);
                })->sortBy(function ($staff) use ($staffWorkload) {
                    return $staffWorkload[$staff->id]['assigned'];
                });

                // If no preferred staff, use any available staff
                if ($preferredStaff->isEmpty()) {
                    $preferredStaff = collect($staffWorkload)->sortBy('assigned')->pluck('staff');
                }

                // Assign rooms to staff
                foreach ($rooms as $room) {
                    $assigned = false;

                    foreach ($preferredStaff as $staff) {
                        if ($staffWorkload[$staff->id]['assigned'] < $staffWorkload[$staff->id]['max']) {
                            $this->assignRoom($room, $staff, $date);
                            $staffWorkload[$staff->id]['assigned']++;
                            $staffWorkload[$staff->id]['floors'][] = $floor;
                            $assignedCount++;
                            $assigned = true;
                            break;
                        }
                    }

                    if (!$assigned) {
                        // Find any staff with capacity
                        foreach ($staffWorkload as $staffId => $workload) {
                            if ($workload['assigned'] < $workload['max']) {
                                $this->assignRoom($room, $workload['staff'], $date);
                                $staffWorkload[$staffId]['assigned']++;
                                $staffWorkload[$staffId]['floors'][] = $floor;
                                $assignedCount++;
                                break;
                            }
                        }
                    }
                }
            }

            DB::commit();

            // Notify via WebSocket
            $this->websocket->notifyAssignmentsUpdated($hotelId, $date);

            return [
                'assigned' => $assignedCount,
                'unassigned' => $totalRooms - $assignedCount,
                'staff_assignments' => collect($staffWorkload)->map(function ($workload) {
                    return [
                        'staff' => $workload['staff']->display_name,
                        'assigned' => $workload['assigned'],
                        'floors' => array_unique($workload['floors'])
                    ];
                })->values()
            ];
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Get rooms that need cleaning.
     */
    protected function getRoomsToClean($hotelId)
    {
        return Room::where('hotel_id', $hotelId)
            ->whereIn('status', ['libre_sale', 'occupee_sale'])
            ->whereDoesntHave('assignments', function ($query) {
                $query->where('assigned_date', today())
                    ->whereNotIn('status', ['cancelled']);
            })
            ->orderBy('floor')
            ->orderBy('number')
            ->get();
    }

    /**
     * Get available staff for a date.
     */
    protected function getAvailableStaff($hotelId, Carbon $date)
    {
        // Get all active staff for the hotel
        $allStaff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('active', true)
            ->get();

        // Filter out staff that have reached their max capacity
        $availableStaff = $allStaff->filter(function ($staff) use ($date, $hotelId) {
            $assignedCount = DB::table('room_assignments')
                ->where('assigned_date', $date)
                ->where('hotel_id', $hotelId)
                ->where('staff_id', $staff->id)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            return $assignedCount < $staff->max_rooms_per_day;
        });

        \Log::info('Staff availability check', [
            'total_active_staff' => $allStaff->count(),
            'available_staff' => $availableStaff->count(),
            'date' => $date->format('Y-m-d')
        ]);

        return $availableStaff;
    }

    /**
     * Assign a room to a staff member.
     */
    protected function assignRoom(Room $room, HousekeepingStaff $staff, Carbon $date)
    {
        return RoomAssignment::create([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'staff_id' => $staff->id,
            'assigned_date' => $date,
            'assigned_at' => now(),
            'status' => RoomAssignment::STATUS_PENDING
        ]);
    }

    /**
     * Reassign a room to another staff member.
     */
    public function reassignRoom(RoomAssignment $assignment, HousekeepingStaff $newStaff)
    {
        if ($assignment->status !== RoomAssignment::STATUS_PENDING) {
            throw new \Exception('Can only reassign pending assignments');
        }

        $assignment->update([
            'staff_id' => $newStaff->id,
            'assigned_at' => now()
        ]);

        // Notify via WebSocket
        $this->websocket->notifyAssignmentReassigned(
            $assignment->hotel_id,
            $assignment->id,
            $newStaff->id
        );

        return $assignment;
    }

    /**
     * Get assignments for a specific date.
     */
    public function getAssignmentsForDate($hotelId, Carbon $date)
    {
        return RoomAssignment::with(['room.roomType', 'staff.user'])
            ->where('hotel_id', $hotelId)
            ->where('assigned_date', $date)
            ->orderBy('staff_id')
            ->orderBy('assigned_at')
            ->get()
            ->groupBy('staff_id');
    }

    /**
     * Get staff performance metrics.
     */
    public function getStaffMetrics($hotelId, Carbon $startDate, Carbon $endDate)
    {
        return HousekeepingStaff::where('hotel_id', $hotelId)
            ->with(['assignments' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('assigned_date', [$startDate, $endDate])
                    ->where('status', RoomAssignment::STATUS_VALIDATED);
            }])
            ->get()
            ->map(function ($staff) {
                $assignments = $staff->assignments;
                return [
                    'staff' => $staff,
                    'total_rooms' => $assignments->count(),
                    'average_duration' => $assignments->avg('duration_minutes'),
                    'total_duration' => $assignments->sum('duration_minutes'),
                    'rooms_per_day' => $assignments->groupBy('assigned_date')->map->count()->avg()
                ];
            });
    }
}
