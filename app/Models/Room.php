<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToHotel;
use Illuminate\Support\Facades\Auth;
use App\Services\WebSocketService;

class Room extends Model
{
    use HasFactory, BelongsToHotel;

    const STATUS_LIBRE_PROPRE = 'libre_propre';
    const STATUS_LIBRE_SALE = 'libre_sale';
    const STATUS_OCCUPEE_PROPRE = 'occupee_propre';
    const STATUS_OCCUPEE_SALE = 'occupee_sale';
    const STATUS_EN_NETTOYAGE = 'en_nettoyage';
    const STATUS_HORS_SERVICE = 'hors_service';
    const STATUS_RESERVEE = 'reservee';

    protected $fillable = [
        'hotel_id',
        'number',
        'room_type_id',
        'floor',
        'status',
        'features',
        'notes',
    ];

    protected $casts = [
        'floor' => 'integer',
        'features' => 'array',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function currentReservation()
    {
        return $this->hasOne(Reservation::class)
            ->where('status', 'en_cours')
            ->whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>=', now());
    }

    public function nextReservation()
    {
        return $this->hasOne(Reservation::class)
            ->where('status', 'confirmee')
            ->whereDate('check_in_date', '>', now())
            ->orderBy('check_in_date', 'asc');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RoomStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(RoomNote::class)->byPriority();
    }

    public function activeNotes(): HasMany
    {
        return $this->notes()->where('active', true);
    }

    public function isAvailable($checkIn, $checkOut): bool
    {
        if (!in_array($this->status, [self::STATUS_LIBRE_PROPRE, self::STATUS_LIBRE_SALE])) {
            return false;
        }

        return !$this->reservations()
            ->whereIn('status', ['confirmee', 'en_cours'])
            ->where(function ($query) use ($checkIn, $checkOut) {
                $query->whereBetween('check_in_date', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                    ->orWhere(function ($q) use ($checkIn, $checkOut) {
                        $q->where('check_in_date', '<=', $checkIn)
                            ->where('check_out_date', '>=', $checkOut);
                    });
            })
            ->exists();
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_LIBRE_PROPRE => 'Libre propre',
            self::STATUS_LIBRE_SALE => 'Libre sale',
            self::STATUS_OCCUPEE_PROPRE => 'Occupée propre',
            self::STATUS_OCCUPEE_SALE => 'Occupée sale',
            self::STATUS_EN_NETTOYAGE => 'En nettoyage',
            self::STATUS_HORS_SERVICE => 'Hors service',
            self::STATUS_RESERVEE => 'Réservée',
        ];
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_LIBRE_PROPRE => 'green',
            self::STATUS_LIBRE_SALE => 'yellow',
            self::STATUS_OCCUPEE_PROPRE => 'blue',
            self::STATUS_OCCUPEE_SALE => 'orange',
            self::STATUS_EN_NETTOYAGE => 'purple',
            self::STATUS_HORS_SERVICE => 'red',
            self::STATUS_RESERVEE => 'gray',
            default => 'gray',
        };
    }

    protected static function booted()
    {
        static::updating(function ($room) {
            if ($room->isDirty('status') && Auth::check()) {
                RoomStatusHistory::create([
                    'hotel_id' => $room->hotel_id,
                    'room_id' => $room->id,
                    'previous_status' => $room->getOriginal('status'),
                    'new_status' => $room->status,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);

                // Notify via WebSocket
                try {
                    $websocket = app(WebSocketService::class);
                    $websocket->notifyRoomStatusChange(
                        $room->hotel_id,
                        $room->load(['roomType', 'currentReservation.guest'])->toArray(),
                        $room->getOriginal('status'),
                        $room->status
                    );
                } catch (\Exception $e) {
                    \Log::error('Failed to send WebSocket notification', ['error' => $e->getMessage()]);
                }
            }
        });
    }

    public function getPriorityAttribute(): string
    {
        // Priorité urgente si note urgente
        if ($this->activeNotes()->where('priority', 'urgent')->exists()) {
            return 'urgent';
        }

        // Priorité haute si départ aujourd'hui
        if ($this->currentReservation && $this->currentReservation->check_out_date->isToday()) {
            return 'high';
        }

        // Priorité haute si arrivée prévue et chambre sale
        $arrivalsToday = $this->reservations()
            ->where('status', 'confirmee')
            ->whereDate('check_in_date', today())
            ->exists();
            
        if ($arrivalsToday && in_array($this->status, [self::STATUS_LIBRE_SALE, self::STATUS_OCCUPEE_SALE])) {
            return 'high';
        }

        // Priorité normale par défaut
        return 'normal';
    }

    /**
     * Get the assignments for the room.
     */
    public function assignments()
    {
        return $this->hasMany(RoomAssignment::class);
    }

    /**
     * Get today's assignment for the room.
     */
    public function todayAssignment()
    {
        return $this->hasOne(RoomAssignment::class)
            ->where('assigned_date', today())
            ->orderBy('assigned_at', 'desc');
    }
}