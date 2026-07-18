<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomNote;
use App\Models\RoomAssignment;
use Illuminate\Http\Request;
use App\Services\WebSocketService;
use App\Services\PriorityCalculationService;

class RoomController extends Controller
{
    protected WebSocketService $websocket;
    protected PriorityCalculationService $priorityCalculationService;

    public function __construct(WebSocketService $websocket, PriorityCalculationService $priorityCalculationService)
    {
        $this->websocket = $websocket;
        $this->priorityCalculationService = $priorityCalculationService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Room::with(['roomType', 'currentReservation.guest', 'nextReservation.guest']);

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

        $rooms = $query->orderBy('number')->get();

        $enrichedRooms = $rooms->map(function ($room) {
            return [
                ...$room->toArray(),
                'priority' => $this->priorityCalculationService->computeDynamicPriority($room)
            ];
        });

        return response()->json($enrichedRooms); // ← Retourner les chambres enrichi
    }


      public function calculatePriority(Room $room)
    {
        $priority = $this->priorityCalculationService->computeDynamicPriority($room);

        return response()->json([
            'room_id' => $room->id,
            'room_number' => $room->number,
            'priority' => $priority,
            'calculated_at' => now()->toISOString()
        ]);
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

        $room->load(['roomType', 'currentReservation.guest', 'nextReservation.guest']);

        return response()->json($room, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Room $room)
    {
        $room->load(['roomType', 'currentReservation.guest', 'nextReservation.guest']);

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

        $oldStatus = $room->status;
        $newStatus = $request->status ?? $oldStatus;

        // Mettre à jour la chambre
        $room->update($request->only(['status', 'notes']));

        // Si la chambre passe d'un statut "sale" à un statut "non sale"
        $dirtyStatuses = [Room::STATUS_LIBRE_SALE, Room::STATUS_OCCUPEE_SALE];
        $wasCleaningNeeded = in_array($oldStatus, $dirtyStatuses);
        $isCleaningNotNeeded = !in_array($newStatus, $dirtyStatuses);

        if ($wasCleaningNeeded && $isCleaningNotNeeded && $oldStatus !== $newStatus) {
            // Retirer toute attribution active pour cette chambre
            $activeAssignment = RoomAssignment::where('room_id', $room->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereDate('assigned_date', today())
                ->first();

            if ($activeAssignment) {
                $activeAssignment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => 'Chambre marquée comme propre'
                ]);

                // Log de l'action
                Log::info('Room assignment cancelled due to status change', [
                    'room_id' => $room->id,
                    'room_number' => $room->number,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'assignment_id' => $activeAssignment->id,
                    'staff_id' => $activeAssignment->staff_id
                ]);

                // Notifier via WebSocket si nécessaire
                if ($this->websocket) {
                    $this->websocket->notifyAssignmentCancelled(
                        $room->hotel_id,
                        $activeAssignment->id,
                        'Chambre marquée comme propre'
                    );
                }
            }
        }
        
        // Recharger les relations après la mise à jour
        $room->load(['roomType', 'currentReservation.guest', 'nextReservation.guest']);

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

    /**
     * Get room status history.
     */
    public function history(Room $room)
    {
        $history = $room->statusHistory()
            ->with('user:id,name,email')
            ->take(50)
            ->get();

        return response()->json($history);
    }

    /**
     * Get room notes.
     */
    public function getNotes(Room $room)
    {
        $notes = $room->activeNotes()
            ->with('user:id,name')
            ->get();

        return response()->json($notes);
    }

    /**
     * Add a note to a room.
     */
    public function addNote(Request $request, Room $room)
    {
        $request->validate([
            'note' => 'required|string|max:1000',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $note = $room->notes()->create([
            'hotel_id' => $room->hotel_id,
            'note' => $request->note,
            'priority' => $request->priority ?? 'normal',
            'created_by' => auth()->id(),
            'expires_at' => $request->expires_at,
        ]);

        $note->load('user:id,name');

        // Notify via WebSocket
        $this->websocket->notifyRoomNoteAdded(
            $room->hotel_id,
            $room->id,
            $note->toArray()
        );

        return response()->json($note, 201);
    }

    /**
     * Delete a room note.
     */
    public function deleteNote(Room $room, RoomNote $note)
    {
        if ($note->room_id !== $room->id) {
            return response()->json(['message' => 'Note not found'], 404);
        }

        $note->update(['active' => false]);

        // Notify via WebSocket
        $this->websocket->notifyRoomNoteDeleted(
            $room->hotel_id,
            $room->id,
            $note->id
        );

        return response()->json(['message' => 'Note supprimée avec succès']);
    }




}