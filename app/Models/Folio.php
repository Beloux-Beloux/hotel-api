<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Traits\BelongsToHotel;

class Folio extends Model
{
    use HasFactory, BelongsToHotel;

    const STATUS_OUVERT = 'ouvert';
    const STATUS_FERME = 'ferme';

    protected $fillable = [
        'hotel_id',
        'folio_number',
        'reservation_id',
        'guest_id',
        'opened_at',
        'closed_at',
        'status',
        'total_amount',
        'paid_amount',
        'currency',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($folio) {
            if (empty($folio->folio_number)) {
                $folio->folio_number = self::generateFolioNumber();
            }
            if (empty($folio->opened_at)) {
                $folio->opened_at = now();
            }
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }

    public static function generateFolioNumber(): string
    {
        do {
            $number = 'FOL' . date('Y') . strtoupper(Str::random(6));
        } while (self::where('folio_number', $number)->exists());

        return $number;
    }

    public function getBalanceAttribute(): float
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->status === self::STATUS_OUVERT;
    }

    public function close(): void
    {
        $this->update([
            'status' => self::STATUS_FERME,
            'closed_at' => now(),
        ]);
    }

    public function addItem(array $data): FolioItem
    {
        return $this->items()->create($data);
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->sum('amount');
        $this->update(['total_amount' => $total]);
    }
}