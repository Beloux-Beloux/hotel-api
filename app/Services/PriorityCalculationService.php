<?php

namespace App\Services;

use App\Models\Room;
use App\Models\PriorityRule;
use App\Models\Reservation;
use Carbon\Carbon;
use App\Services\ReservationService;

class PriorityCalculationService
{
    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function computeDynamicPriority(Room $room): string
    {
        $rules = PriorityRule::active()->byHotel($room->hotel_id)->get();
        $totalScore = 0;

        foreach ($rules as $rule) {
            if ($rule->evaluate($room, $this->reservationService)) {
                $totalScore += $rule->weight;
            }
        }

        return $this->determinePriorityLevel($totalScore);
    }

    private function determinePriorityLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'urgent',
            $score >= 50 => 'high',
            default => 'normal'
        };
    }

    // Méthodes de fallback pour la compatibilité
    public function calculateLibreSalePriority(Room $room, Carbon $today): string
    {
        $nextReservation = $this->reservationService->getNextReservation($room->id);
        
        if (!$nextReservation) {
            return 'normal';
        }
        
        $checkInDate = Carbon::parse($nextReservation->check_in_date);
        
        if ($checkInDate->isSameDay($today)) {
            return 'urgent';
        }
        
        if ($checkInDate->isSameDay($today->copy()->addDay())) {
            return 'high';
        }
        
        return 'normal';
    }

    public function calculateOccupeeSalePriority(Room $room, Carbon $today): string
    {
        $currentReservation = $this->reservationService->getCurrentReservation($room->id);
        
        if (!$currentReservation) {
            return 'normal';
        }
        
        $checkOutDate = Carbon::parse($currentReservation->check_out_date);
        
        if ($checkOutDate->isSameDay($today)) {
            return 'urgent';
        }
        
        return 'normal';
    }
}