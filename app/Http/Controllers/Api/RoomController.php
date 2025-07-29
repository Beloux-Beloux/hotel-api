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
        $query = Room::with(['roomType', 'currentReservation.guest']);

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'number' => 'required|string|unique:rooms,number,NULL,id,hotel_id,' . session('current_hotel_id'),
            'floor' => 'required|integer|min:0',
            'room_type_id' => 'required|exists:room_types,id',
            'status' => 'sometimes|in:' . implode(',', array_keys(Room::getStatusOptions())),
            'notes' => 'nullable|string|max:500',
        ]);

        $room = Room::create([
            'hotel_id' => session('current_hotel_id'),
            'number' => $request->number,
            'floor' => $request->floor,
            'room_type_id' => $request->room_type_id,
            'status' => $request->status ?? 'libre_propre',
            'notes' => $request->notes,
        ]);

        $room->load(['roomType', 'currentReservation.guest']);

        return response()->json($room, 201);
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
        
        // Recharger les relations après la mise à jour
        $room->load(['roomType', 'currentReservation.guest']);

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Room $room)
    {
        // Vérifier s'il y a des réservations en cours
        if ($room->currentReservation) {
            return response()->json([
                'message' => 'Impossible de supprimer cette chambre car elle a une réservation en cours.'
            ], 409);
        }

        // Vérifier s'il y a un historique de réservations
        if ($room->reservations()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer cette chambre car elle a un historique de réservations.'
            ], 409);
        }

        $room->delete();

        return response()->json([
            'message' => 'Chambre supprimée avec succès.'
        ]);
    }
}