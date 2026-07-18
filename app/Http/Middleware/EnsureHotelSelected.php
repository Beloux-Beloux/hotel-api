<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if user has selected a hotel
        if (!auth()->user()->current_hotel_id) {
            return response()->json([
                'message' => 'No hotel selected. Please select a hotel first.',
                'error' => 'no_hotel_selected',
            ], 400);
        }

        // Check if the selected hotel is active
        $currentHotel = auth()->user()->currentHotel;
        if (!$currentHotel || !$currentHotel->is_active) {
            return response()->json([
                'message' => 'The selected hotel is not active.',
                'error' => 'hotel_inactive',
            ], 403);
        }

        // Check if the hotel subscription is active
        if (!$currentHotel->isSubscriptionActive()) {
            return response()->json([
                'message' => 'Hotel subscription has expired.',
                'error' => 'subscription_expired',
            ], 403);
        }

        // Set timezone for the request based on hotel settings
        if ($currentHotel->timezone) {
            config(['app.timezone' => $currentHotel->timezone]);
            date_default_timezone_set($currentHotel->timezone);
        }

        // Add hotel information to the request for easy access
        $request->merge(['current_hotel' => $currentHotel]);

        return $next($request);
    }
}