<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\ReservationAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Get rooms statistics
        $totalRooms = Room::count();
        $occupiedRooms = Room::where('status', 'occupee_propre')->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 1) : 0;

        // Get today's arrivals and departures
        $arrivalsToday = Reservation::where('check_in_date', $today)
            ->whereIn('status', ['confirmee', 'garantie'])
            ->count();

        $departuresToday = Reservation::where('check_out_date', $today)
            ->where('status', 'en_cours')
            ->count();

        // Calculate today's revenue
        $revenueToday = Reservation::whereDate('created_at', $today)
            ->whereIn('status', ['confirmee', 'garantie', 'en_cours'])
            ->sum('total_amount');

        // Calculate yesterday's revenue for trend
        $revenueYesterday = Reservation::whereDate('created_at', $yesterday)
            ->whereIn('status', ['confirmee', 'garantie', 'en_cours'])
            ->sum('total_amount');

        $revenueTrend = $revenueYesterday > 0 
            ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1)
            : 0;

        return response()->json([
            'rooms_occupied' => $occupiedRooms,
            'rooms_total' => $totalRooms,
            'occupancy_rate' => $occupancyRate,
            'arrivals_today' => $arrivalsToday,
            'departures_today' => $departuresToday,
            'revenue_today' => $revenueToday,
            'revenue_trend' => $revenueTrend,
        ]);
    }

    public function activities(): JsonResponse
    {
        $activities = [];
        
        // Get recent check-ins
        $recentCheckins = Reservation::with('guest')
            ->where('status', 'en_cours')
            ->whereDate('check_in_date', '>=', Carbon::now()->subDays(1))
            ->orderBy('check_in_date', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentCheckins as $checkin) {
            $activities[] = [
                'id' => $checkin->id,
                'type' => 'checkin',
                'message' => "Check-in: {$checkin->guest->full_name} - Chambre {$checkin->room->number}",
                'time' => Carbon::parse($checkin->check_in_date)->diffForHumans(),
                'created_at' => $checkin->check_in_date,
            ];
        }

        // Get recent reservations
        $recentReservations = Reservation::with('guest')
            ->whereIn('status', ['confirmee', 'garantie'])
            ->whereDate('created_at', '>=', Carbon::now()->subDays(1))
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentReservations as $reservation) {
            $nights = Carbon::parse($reservation->check_in_date)->diffInDays(Carbon::parse($reservation->check_out_date));
            $activities[] = [
                'id' => $reservation->id,
                'type' => 'reservation',
                'message' => "Nouvelle réservation: {$reservation->guest->full_name} - {$nights} nuits",
                'time' => $reservation->created_at->diffForHumans(),
                'created_at' => $reservation->created_at,
            ];
        }

        // Get maintenance requests (rooms out of service)
        $maintenanceRooms = Room::where('status', 'hors_service')
            ->whereDate('updated_at', '>=', Carbon::now()->subDays(1))
            ->limit(2)
            ->get();

        foreach ($maintenanceRooms as $room) {
            $activities[] = [
                'id' => $room->id,
                'type' => 'maintenance',
                'message' => "Maintenance requise: Chambre {$room->number}" . ($room->notes ? " - {$room->notes}" : ""),
                'time' => $room->updated_at->diffForHumans(),
                'created_at' => $room->updated_at,
            ];
        }

        // Sort activities by created_at descending
        usort($activities, function ($a, $b) {
            return Carbon::parse($b['created_at'])->timestamp - Carbon::parse($a['created_at'])->timestamp;
        });

        // Return only the most recent 10 activities
        return response()->json(array_slice($activities, 0, 10));
    }
}