<?php

namespace App\Services;

use App\Models\Reservation;

class ReservationService
{
    public function getCurrentReservation($roomId)
    {
        return Reservation::where('room_id', $roomId)
            ->whereIn('status', [Reservation::STATUS_EN_COURS])
            ->whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>=', now())
            ->first();
    }

    public function getNextReservation($roomId)
    {
        return Reservation::where('room_id', $roomId)
            ->whereIn('status', [Reservation::STATUS_CONFIRMEE])
            ->whereDate('check_in_date', '>', now())
            ->orderBy('check_in_date', 'asc')
            ->first();
    }

    public function hasCurrentReservation($roomId): bool
    {
        return $this->getCurrentReservation($roomId) !== null;
    }

    public function hasNextReservation($roomId): bool
    {
        return $this->getNextReservation($roomId) !== null;
    }
}