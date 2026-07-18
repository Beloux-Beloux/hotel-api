<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Guest::query();

        // Search functionality
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Filter by VIP status
        if ($request->has('vip')) {
            $query->where('vip_status', $request->boolean('vip'));
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $guests = $query->paginate($perPage);

        return response()->json($guests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:guests,email',
            'phone' => 'nullable|string|max:50',
            'id_type' => 'nullable|string|max:50',
            'id_number' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|size:2',
            'address' => 'nullable|array',
            'preferences' => 'nullable|array',
            'vip_status' => 'boolean',
        ]);

        $guest = Guest::create($validated);

        return response()->json($guest, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Guest $guest)
    {
        $guest->load(['reservations' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $guest->loadCount('reservations');
        $guest->append(['total_spent', 'last_visit']);

        return response()->json($guest);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Guest $guest)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|unique:guests,email,' . $guest->id,
            'phone' => 'sometimes|nullable|string|max:50',
            'id_type' => 'sometimes|nullable|string|max:50',
            'id_number' => 'sometimes|nullable|string|max:100',
            'nationality' => 'sometimes|nullable|string|size:2',
            'address' => 'sometimes|nullable|array',
            'preferences' => 'sometimes|nullable|array',
            'vip_status' => 'sometimes|boolean',
        ]);

        $guest->update($validated);

        return response()->json($guest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Guest $guest)
    {
        // Check if guest has reservations
        if ($guest->reservations()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un client avec des réservations.',
            ], 422);
        }

        $guest->delete();

        return response()->json(null, 204);
    }

    /**
     * Search guests.
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $guests = Guest::search($request->get('query'))
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        return response()->json($guests);
    }

    /**
     * Get guest statistics.
     */
    public function statistics(Guest $guest)
    {
        $stats = [
            'total_reservations' => $guest->reservations()->count(),
            'active_reservations' => $guest->reservations()->active()->count(),
            'total_spent' => $guest->total_spent,
            'last_visit' => $guest->last_visit,
            'average_stay_duration' => $guest->reservations()
                ->where('status', 'terminee')
                ->selectRaw('AVG(DATEDIFF(check_out_date, check_in_date)) as avg_duration')
                ->value('avg_duration'),
            'preferred_room_type' => $guest->reservations()
                ->join('rooms', 'reservations.room_id', '=', 'rooms.id')
                ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
                ->select('room_types.name')
                ->groupBy('room_types.id', 'room_types.name')
                ->orderByRaw('COUNT(*) DESC')
                ->value('name'),
        ];

        return response()->json($stats);
    }
}