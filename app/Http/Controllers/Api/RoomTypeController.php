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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:room_types,code,NULL,id,hotel_id,' . session('current_hotel_id'),
            'base_price' => 'required|numeric|min:0',
            'max_occupancy' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:50',
        ]);

        $roomType = RoomType::create([
            'hotel_id' => session('current_hotel_id'),
            'name' => $request->name,
            'code' => $request->code,
            'base_price' => $request->base_price,
            'max_occupancy' => $request->max_occupancy,
            'description' => $request->description,
            'amenities' => $request->amenities ?? [],
        ]);

        $roomType->loadCount(['rooms' => function ($query) {
            $query->where('status', 'libre_propre');
        }]);

        return response()->json($roomType, 201);
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, RoomType $roomType)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:10|unique:room_types,code,' . $roomType->id . ',id,hotel_id,' . session('current_hotel_id'),
            'base_price' => 'sometimes|numeric|min:0',
            'max_occupancy' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'amenities' => 'nullable|array',
            'amenities.*' => 'string|max:50',
        ]);

        $roomType->update($request->only([
            'name', 'code', 'base_price', 'max_occupancy', 'description', 'amenities'
        ]));

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RoomType $roomType)
    {
        // Vérifier s'il y a des chambres associées
        if ($roomType->rooms()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce type de chambre car des chambres y sont associées.'
            ], 409);
        }

        // Vérifier s'il y a des réservations passées ou futures
        $hasReservations = $roomType->rooms()->whereHas('reservations')->exists();
        if ($hasReservations) {
            return response()->json([
                'message' => 'Impossible de supprimer ce type de chambre car il a un historique de réservations.'
            ], 409);
        }

        $roomType->delete();

        return response()->json([
            'message' => 'Type de chambre supprimé avec succès.'
        ]);
    }
    public function getByHotel($hotelId)
    {
        $roomTypes = RoomType::where('hotel_id', $hotelId)->get();
        return response()->json($roomTypes);
    }
}