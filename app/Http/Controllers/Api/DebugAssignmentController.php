<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\HousekeepingStaff;
use App\Models\RoomAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DebugAssignmentController extends Controller
{
    public function debug(Request $request)
    {
        $hotelId = auth()->user()->current_hotel_id;
        $date = Carbon::parse($request->get('date', today()));

        // Get rooms that need cleaning
        $roomsToClean = Room::where('hotel_id', $hotelId)
            ->whereIn('status', ['libre_sale', 'occupee_sale'])
            ->get();

        // Check existing assignments for these rooms
        $roomsWithAssignments = RoomAssignment::where('hotel_id', $hotelId)
            ->where('assigned_date', $date)
            ->whereIn('room_id', $roomsToClean->pluck('id'))
            ->whereNotIn('status', ['cancelled'])
            ->get();

        // Get available staff
        $allStaff = HousekeepingStaff::where('hotel_id', $hotelId)->get();
        
        $activeStaff = HousekeepingStaff::where('hotel_id', $hotelId)
            ->where('active', true)
            ->get();

        // Get staff at max capacity
        $staffAtMaxCapacity = DB::table('room_assignments')
            ->where('assigned_date', $date)
            ->where('hotel_id', $hotelId)
            ->select('staff_id', DB::raw('COUNT(*) as count'))
            ->groupBy('staff_id')
            ->get();

        // Get staff with capacity check
        $staffWithCapacity = [];
        foreach ($activeStaff as $staff) {
            $assignmentCount = RoomAssignment::where('hotel_id', $hotelId)
                ->where('staff_id', $staff->id)
                ->where('assigned_date', $date)
                ->count();
            
            $staffWithCapacity[] = [
                'id' => $staff->id,
                'name' => $staff->display_name,
                'active' => $staff->active,
                'max_rooms' => $staff->max_rooms_per_day,
                'current_assignments' => $assignmentCount,
                'has_capacity' => $assignmentCount < $staff->max_rooms_per_day
            ];
        }

        return response()->json([
            'hotel_id' => $hotelId,
            'date' => $date->format('Y-m-d'),
            'rooms_needing_cleaning' => [
                'count' => $roomsToClean->count(),
                'rooms' => $roomsToClean->map(fn($r) => [
                    'id' => $r->id,
                    'number' => $r->number,
                    'status' => $r->status,
                    'floor' => $r->floor
                ])
            ],
            'existing_assignments' => [
                'count' => $roomsWithAssignments->count(),
                'assignments' => $roomsWithAssignments->map(fn($a) => [
                    'room_id' => $a->room_id,
                    'staff_id' => $a->staff_id,
                    'status' => $a->status
                ])
            ],
            'staff' => [
                'total' => $allStaff->count(),
                'active' => $activeStaff->count(),
                'staff_details' => $staffWithCapacity,
                'at_max_capacity' => $staffAtMaxCapacity
            ]
        ]);
    }
}