<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Room::with('roomType');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by floor
        if ($request->has('floor')) {
            $query->where('floor', $request->floor);
        }

        // Filter by room type
        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $rooms = $query->orderBy('number')->get();

        return response()->json($rooms);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        $room->load(['roomType', 'currentReservation.guest']);

        return response()->json($room);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Room $room)
    {
        $request->validate([
            'status' => 'sometimes|in:' . implode(',', array_keys(Room::getStatusOptions())),
            'notes' => 'sometimes|nullable|string',
        ]);

        $room->update($request->only(['status', 'notes']));

        return response()->json($room);
    }

    /**
     * Get room availability.
     */
    public function availability(Request $request)
    {
        $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'room_type_id' => 'sometimes|exists:room_types,id',
        ]);

        $query = Room::with('roomType');

        if ($request->has('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        $rooms = $query->get()->filter(function ($room) use ($request) {
            return $room->isAvailable($request->check_in, $request->check_out);
        })->values();

        return response()->json($rooms);
    }

    /**
     * Get room statistics.
     */
    public function statistics()
    {
        $stats = [
            'total_rooms' => Room::count(),
            'by_status' => Room::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_type' => Room::selectRaw('room_type_id, count(*) as count')
                ->with('roomType:id,name')
                ->groupBy('room_type_id')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->roomType->name => $item->count];
                }),
            'occupancy_rate' => round(
                (Room::whereIn('status', ['occupee_propre', 'occupee_sale'])->count() / Room::count()) * 100,
                2
            ),
        ];

        return response()->json($stats);
    }
}