<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roomTypes = RoomType::withCount(['rooms' => function ($query) {
            $query->where('status', 'libre_propre');
        }])->get();

        return response()->json($roomTypes);
    }

    /**
     * Display the specified resource.
     */
    public function show(RoomType $roomType)
    {
        $roomType->loadCount(['rooms' => function ($query) {
            $query->where('status', 'libre_propre');
        }]);

        return response()->json($roomType);
    }

    /**
     * Get availability for a room type between dates.
     */
    public function availability(Request $request, RoomType $roomType)
    {
        $request->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        $availableCount = $roomType->getAvailableRoomsCount(
            $request->check_in,
            $request->check_out
        );

        return response()->json([
            'room_type' => $roomType,
            'available_rooms' => $availableCount,
            'check_in' => $request->check_in,
            'check_out' => $request->check_out,
        ]);
    }
}