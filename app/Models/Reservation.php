<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Models\Traits\BelongsToHotel;

class Reservation extends Model
{
    use HasFactory, BelongsToHotel;

    const STATUS_CONFIRMEE = 'confirmee';
    const STATUS_EN_COURS = 'en_cours';
    const STATUS_TERMINEE = 'terminee';
    const STATUS_ANNULEE = 'annulee';
    const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'hotel_id',
        'booking_number',
        'guest_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'adults',
        'children',
        'status',
        'room_rate',
        'total_amount',
        'currency',
        'deposit_amount',
        'special_requests',
        'source',
        'created_by',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'room_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->booking_number)) {
                $reservation->booking_number = self::generateBookingNumber();
            }
        });
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function folio(): HasOne
    {
        return $this->hasOne(Folio::class);
    }

    public function auditTrails(): HasMany
    {
        return $this->hasMany(ReservationAudit::class);
    }

    public static function generateBookingNumber(): string
    {
        do {
            $number = 'RES' . date('Y') . strtoupper(Str::random(6));
        } while (self::where('booking_number', $number)->exists());

        return $number;
    }

    public function getNightsAttribute(): int
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function getTotalGuestsAttribute(): int
    {
        return $this->adults + $this->children;
    }

    public function getCanModifyAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMEE]) 
            && $this->check_in_date->isAfter(now()->addDay());
    }

    public function getCanCancelAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_CONFIRMEE]) 
            && $this->check_in_date->isAfter(now());
    }

    public function calculateTotalAmount(): float
    {
        return $this->room_rate * $this->nights;
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_CONFIRMEE => 'Confirmée',
            self::STATUS_EN_COURS => 'En cours',
            self::STATUS_TERMINEE => 'Terminée',
            self::STATUS_ANNULEE => 'Annulée',
            self::STATUS_NO_SHOW => 'No show',
        ];
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CONFIRMEE => 'blue',
            self::STATUS_EN_COURS => 'green',
            self::STATUS_TERMINEE => 'gray',
            self::STATUS_ANNULEE => 'red',
            self::STATUS_NO_SHOW => 'orange',
            default => 'gray',
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMEE, self::STATUS_EN_COURS]);
    }

    public function scopeArrivals($query, $date = null)
    {
        $date = $date ?: today();
        return $query->where('status', self::STATUS_CONFIRMEE)
            ->whereDate('check_in_date', $date);
    }

    public function scopeDepartures($query, $date = null)
    {
        $date = $date ?: today();
        return $query->where('status', self::STATUS_EN_COURS)
            ->whereDate('check_out_date', $date);
    }
}