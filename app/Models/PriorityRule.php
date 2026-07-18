<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriorityRule extends Model
{
    const TYPE_STATUS = 'status';
    const TYPE_RESERVATION = 'reservation';
    const TYPE_TIME_BASED = 'time_based';
    const TYPE_GUEST_BASED = 'guest_based';

    const OPERATOR_EQUALS = 'equals';
    const OPERATOR_NOT_EQUALS = 'not_equals';
    const OPERATOR_GREATER_THAN = 'greater_than';
    const OPERATOR_LESS_THAN = 'less_than';
    const OPERATOR_EXISTS = 'exists';
    const OPERATOR_NOT_EXISTS = 'not_exists';

    protected $fillable = [
        'hotel_id',
        'name',
        'rule_type',
        'conditions',
        'priority_level',
        'weight',
        'is_active',
        'description'
    ];

    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'weight' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByHotel($query, $hotelId)
    {
        return $query->where('hotel_id', $hotelId);
    }

    public function evaluate($room, $reservationService)
    {
        $conditions = $this->conditions;
        
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($room, $condition, $reservationService)) {
                return false;
            }
        }
        
        return true;
    }

    private function evaluateCondition($room, $condition, $reservationService)
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        $roomValue = $this->getRoomValue($room, $field, $reservationService);

        switch ($operator) {
            case self::OPERATOR_EQUALS:
                return $roomValue == $value;
            case self::OPERATOR_NOT_EQUALS:
                return $roomValue != $value;
            case self::OPERATOR_GREATER_THAN:
                return $roomValue > $value;
            case self::OPERATOR_LESS_THAN:
                return $roomValue < $value;
            case self::OPERATOR_EXISTS:
                return !empty($roomValue);
            case self::OPERATOR_NOT_EXISTS:
                return empty($roomValue);
            default:
                return false;
        }
    }

    private function getRoomValue($room, $field, $reservationService)
    {
        switch ($field) {
            case 'room_status':
                return $room->status;
            case 'has_current_reservation':
                return $reservationService->hasCurrentReservation($room->id);
            case 'has_next_reservation':
                return $reservationService->hasNextReservation($room->id);
            case 'check_in_today':
                $nextReservation = $reservationService->getNextReservation($room->id);
                return $nextReservation && $nextReservation->check_in_date->isToday();
            case 'check_out_today':
                $currentReservation = $reservationService->getCurrentReservation($room->id);
                return $currentReservation && $currentReservation->check_out_date->isToday();
            case 'check_in_tomorrow':
                $nextReservation = $reservationService->getNextReservation($room->id);
                return $nextReservation && $nextReservation->check_in_date->isTomorrow();
            case 'days_until_check_in':
                $nextReservation = $reservationService->getNextReservation($room->id);
                return $nextReservation ? $nextReservation->check_in_date->diffInDays(now()) : null;
            case 'guest_type':
                $nextReservation = $reservationService->getNextReservation($room->id);
                return $nextReservation->guest->type ?? null;
            default:
                return null;
        }
    }
}