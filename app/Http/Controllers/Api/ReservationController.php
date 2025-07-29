<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationAudit;
use App\Models\Room;
use App\Models\Folio;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Reservation::with(['guest', 'room.roomType', 'createdBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereBetween('check_in_date', [$request->from_date, $request->to_date]);
        }

        // Filter by guest
        if ($request->has('guest_id')) {
            $query->where('guest_id', $request->guest_id);
        }

        // Sorting
        $sortField = $request->get('sort_by', 'check_in_date');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $reservations = $query->paginate($perPage);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Either existing guest_id or new guest data
            'guest_id' => 'required_without:guest|exists:guests,id',
            'guest' => 'required_without:guest_id|array',
            'guest.first_name' => 'required_with:guest|string|max:100',
            'guest.last_name' => 'required_with:guest|string|max:100',
            'guest.email' => 'nullable|email|unique:guests,email',
            'guest.phone' => 'nullable|string|max:50',
            'guest.id_type' => 'nullable|string|max:50',
            'guest.id_number' => 'nullable|string|max:100',
            'guest.nationality' => 'nullable|string|size:2',
            'guest.address' => 'nullable|array',
            'guest.vip_status' => 'boolean',
            // Reservation data
            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'required|integer|min:0',
            'room_rate' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'special_requests' => 'nullable|string',
            'source' => 'nullable|string',
        ]);

        // Check room availability
        $room = Room::findOrFail($validated['room_id']);
        if (!$room->isAvailable($validated['check_in_date'], $validated['check_out_date'])) {
            return response()->json([
                'message' => 'La chambre n\'est pas disponible pour ces dates.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create guest if needed
            if (isset($validated['guest'])) {
                $guestData = $validated['guest'];
                $guestData['vip_status'] = $guestData['vip_status'] ?? false;
                $guest = Guest::create($guestData);
                $guestId = $guest->id;
            } else {
                $guestId = $validated['guest_id'];
            }

            // Create reservation
            $reservationData = array_diff_key($validated, array_flip(['guest', 'guest_id']));
            $reservation = new Reservation($reservationData);
            $reservation->guest_id = $guestId;
            $reservation->status = Reservation::STATUS_CONFIRMEE;
            $reservation->currency = 'EUR';
            $reservation->created_by = auth()->id();
            $reservation->total_amount = $reservation->calculateTotalAmount();
            $reservation->save();

            // Log creation
            ReservationAudit::logAction($reservation, 'created');

            // Update room status if check-in is today
            if ($reservation->check_in_date->isToday()) {
                $room->update(['status' => 'reservee']);
            }

            DB::commit();

            $reservation->load(['guest', 'room.roomType', 'createdBy']);
            return response()->json($reservation, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de la réservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Reservation $reservation)
    {
        $reservation->load([
            'guest',
            'room.roomType',
            'createdBy',
            'folio.items',
            'auditTrails.user'
        ]);

        return response()->json($reservation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        if (!$reservation->can_modify) {
            return response()->json([
                'message' => 'Cette réservation ne peut plus être modifiée.',
            ], 422);
        }

        $validated = $request->validate([
            'room_id' => 'sometimes|exists:rooms,id',
            'check_in_date' => 'sometimes|date|after_or_equal:today',
            'check_out_date' => 'sometimes|date|after:check_in_date',
            'adults' => 'sometimes|integer|min:1',
            'children' => 'sometimes|integer|min:0',
            'room_rate' => 'sometimes|numeric|min:0',
            'special_requests' => 'sometimes|nullable|string',
        ]);

        // Check room availability if room is changed
        if (isset($validated['room_id']) && $validated['room_id'] != $reservation->room_id) {
            $room = Room::findOrFail($validated['room_id']);
            $checkIn = $validated['check_in_date'] ?? $reservation->check_in_date;
            $checkOut = $validated['check_out_date'] ?? $reservation->check_out_date;
            
            if (!$room->isAvailable($checkIn, $checkOut)) {
                return response()->json([
                    'message' => 'La nouvelle chambre n\'est pas disponible pour ces dates.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $oldValues = $reservation->toArray();
            $reservation->update($validated);
            
            if (isset($validated['room_rate']) || isset($validated['check_in_date']) || isset($validated['check_out_date'])) {
                $reservation->total_amount = $reservation->calculateTotalAmount();
                $reservation->save();
            }

            // Log modification
            ReservationAudit::logAction($reservation, 'updated', $oldValues, $reservation->toArray());

            DB::commit();

            $reservation->load(['guest', 'room.roomType', 'createdBy']);
            return response()->json($reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la modification de la réservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a reservation.
     */
    public function cancel(Reservation $reservation)
    {
        if (!$reservation->can_cancel) {
            return response()->json([
                'message' => 'Cette réservation ne peut pas être annulée.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldStatus = $reservation->status;
            $reservation->update(['status' => Reservation::STATUS_ANNULEE]);

            // Log cancellation
            ReservationAudit::logAction(
                $reservation, 
                'cancelled',
                ['status' => $oldStatus],
                ['status' => Reservation::STATUS_ANNULEE]
            );

            // Update room status if needed
            if ($reservation->room && $reservation->room->status === 'reservee') {
                $reservation->room->update(['status' => 'libre_propre']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Réservation annulée avec succès.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'annulation de la réservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive a reservation.
     */
    public function archive(Reservation $reservation)
    {
        // Only allow archiving of completed, cancelled, or no-show reservations
        if (!in_array($reservation->status, [
            Reservation::STATUS_TERMINEE,
            Reservation::STATUS_ANNULEE,
            Reservation::STATUS_NO_SHOW
        ])) {
            return response()->json([
                'message' => 'Seules les réservations terminées, annulées ou no-show peuvent être archivées.',
            ], 422);
        }

        // Check if already archived
        if ($reservation->is_archived) {
            return response()->json([
                'message' => 'Cette réservation est déjà archivée.',
            ], 422);
        }

        try {
            $reservation->update([
                'is_archived' => true,
                'archived_at' => now(),
            ]);

            return response()->json([
                'message' => 'Réservation archivée avec succès.',
                'reservation' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'archivage de la réservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unarchive a reservation.
     */
    public function unarchive(Reservation $reservation)
    {
        if (!$reservation->is_archived) {
            return response()->json([
                'message' => 'Cette réservation n\'est pas archivée.',
            ], 422);
        }

        try {
            $reservation->update([
                'is_archived' => false,
                'archived_at' => null,
            ]);

            return response()->json([
                'message' => 'Réservation désarchivée avec succès.',
                'reservation' => $reservation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du désarchivage de la réservation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check in a reservation.
     */
    public function checkIn(Request $request, Reservation $reservation)
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMEE) {
            return response()->json([
                'message' => 'Cette réservation ne peut pas être enregistrée.',
            ], 422);
        }

        if (!$reservation->check_in_date->isToday()) {
            return response()->json([
                'message' => 'Le check-in n\'est prévu que pour aujourd\'hui.',
            ], 422);
        }

        $validated = $request->validate([
            'room_id' => 'sometimes|exists:rooms,id',
            'guest_document' => 'sometimes|string',
        ]);

        DB::beginTransaction();
        try {
            // Update reservation status
            $reservation->update([
                'status' => Reservation::STATUS_EN_COURS,
                'room_id' => $validated['room_id'] ?? $reservation->room_id,
            ]);

            // Update room status
            $reservation->room->update(['status' => 'occupee_propre']);

            // Create folio
            $folio = Folio::create([
                'reservation_id' => $reservation->id,
                'guest_id' => $reservation->guest_id,
                'currency' => $reservation->currency,
            ]);

            // Add room charges to folio
            $folio->addItem([
                'description' => 'Hébergement - ' . $reservation->room->roomType->name,
                'quantity' => $reservation->nights,
                'unit_price' => $reservation->room_rate,
                'category' => 'hebergement',
            ]);

            // Log check-in
            ReservationAudit::logAction($reservation, 'checked_in');

            DB::commit();

            $reservation->load(['guest', 'room.roomType', 'folio']);
            return response()->json([
                'message' => 'Check-in effectué avec succès.',
                'reservation' => $reservation,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors du check-in.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get arrivals for a specific date.
     */
    public function arrivals(Request $request)
    {
        $date = $request->get('date', today());
        
        $arrivals = Reservation::arrivals($date)
            ->with(['guest', 'room.roomType'])
            ->get();

        return response()->json($arrivals);
    }

    /**
     * Get departures for a specific date.
     */
    public function departures(Request $request)
    {
        $date = $request->get('date', today());
        
        $departures = Reservation::departures($date)
            ->with(['guest', 'room.roomType', 'folio'])
            ->get();

        return response()->json($departures);
    }
}