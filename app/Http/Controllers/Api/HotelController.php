<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HotelController extends Controller
{
    /**
     * Get all hotels accessible to the user.
     */
    public function index()
    {
        $hotels = auth()->user()->hotels()
            ->where('hotels.is_active', true)
            ->select('hotels.*', 'user_hotels.role as user_role', 'user_hotels.is_default')
            ->get();

        return response()->json($hotels);
    }

    /**
     * Get the current hotel.
     */
    public function current()
    {
        $hotel = auth()->user()->currentHotel;
        
        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel selected.',
            ], 404);
        }

        // Add user role in this hotel
        $userHotel = auth()->user()->hotels()
            ->where('hotel_id', $hotel->id)
            ->first();
        
        $hotel->user_role = $userHotel ? $userHotel->pivot->role : null;

        return response()->json($hotel);
    }

    /**
     * Switch to a different hotel.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
        ]);

        $hotel = Hotel::findOrFail($request->hotel_id);
        
        // Check if user has access to this hotel
        if (!auth()->user()->hasAccessToHotel($hotel)) {
            return response()->json([
                'message' => 'You do not have access to this hotel.',
            ], 403);
        }

        // Check if hotel is active
        if (!$hotel->is_active) {
            return response()->json([
                'message' => 'This hotel is not active.',
            ], 403);
        }

        // Switch hotel
        if (auth()->user()->switchHotel($hotel)) {
            return response()->json([
                'message' => 'Hotel switched successfully.',
                'hotel' => $hotel,
            ]);
        }

        return response()->json([
            'message' => 'Failed to switch hotel.',
        ], 500);
    }

    /**
     * Get hotel statistics (for hotel managers).
     */
    public function statistics(Request $request)
    {
        $hotel = auth()->user()->currentHotel;
        
        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel selected.',
            ], 404);
        }

        // Check if user is manager of this hotel
        $userRole = auth()->user()->getRoleInHotel($hotel);
        if (!in_array($userRole, ['manager', 'owner'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $stats = [
            'rooms' => [
                'total' => $hotel->rooms()->count(),
                'available' => $hotel->rooms()->where('status', 'libre_propre')->count(),
                'occupied' => $hotel->rooms()->whereIn('status', ['occupee_propre', 'occupee_sale'])->count(),
            ],
            'reservations' => [
                'today_arrivals' => $hotel->reservations()
                    ->where('status', 'confirmee')
                    ->whereDate('check_in_date', today())
                    ->count(),
                'today_departures' => $hotel->reservations()
                    ->where('status', 'en_cours')
                    ->whereDate('check_out_date', today())
                    ->count(),
                'upcoming_week' => $hotel->reservations()
                    ->where('status', 'confirmee')
                    ->whereBetween('check_in_date', [now(), now()->addWeek()])
                    ->count(),
            ],
            'revenue' => [
                'today' => $hotel->reservations()
                    ->whereDate('created_at', today())
                    ->sum('total_amount'),
                'this_month' => $hotel->reservations()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total_amount'),
                'last_month' => $hotel->reservations()
                    ->whereMonth('created_at', now()->subMonth()->month)
                    ->whereYear('created_at', now()->subMonth()->year)
                    ->sum('total_amount'),
            ],
            'guests' => [
                'total' => $hotel->guests()->count(),
                'vip' => $hotel->guests()->where('vip_status', true)->count(),
                'new_this_month' => $hotel->guests()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Update hotel settings (for hotel managers).
     */
    public function updateSettings(Request $request)
    {
        $hotel = auth()->user()->currentHotel;
        
        if (!$hotel) {
            return response()->json([
                'message' => 'No hotel selected.',
            ], 404);
        }

        // Check if user is manager of this hotel
        $userRole = auth()->user()->getRoleInHotel($hotel);
        if (!in_array($userRole, ['manager', 'owner'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.check_in_time' => 'sometimes|string',
            'settings.check_out_time' => 'sometimes|string',
            'settings.children_age_limit' => 'sometimes|integer|min:0|max:18',
            'settings.breakfast_included' => 'sometimes|boolean',
            'settings.allow_pets' => 'sometimes|boolean',
            'settings.currency' => 'sometimes|string|size:3',
            'settings.default_room_rate' => 'sometimes|numeric|min:0',
        ]);

        $currentSettings = $hotel->settings ?? [];
        $newSettings = array_merge($currentSettings, $validated['settings']);
        
        $hotel->update(['settings' => $newSettings]);

        return response()->json([
            'message' => 'Settings updated successfully.',
            'settings' => $newSettings,
        ]);
    }
}