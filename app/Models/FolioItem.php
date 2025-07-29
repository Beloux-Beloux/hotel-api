<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'category',
        'date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->amount)) {
                $item->amount = $item->quantity * $item->unit_price;
            }
            if (empty($item->date)) {
                $item->date = today();
            }
        });

        static::created(function ($item) {
            $item->folio->recalculateTotal();
        });

        static::updated(function ($item) {
            $item->folio->recalculateTotal();
        });

        static::deleted(function ($item) {
            $item->folio->recalculateTotal();
        });
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public static function getCategoryOptions(): array
    {
        return [
            'hebergement' => 'Hébergement',
            'restaurant' => 'Restaurant',
            'bar' => 'Bar',
            'spa' => 'Spa',
            'blanchisserie' => 'Blanchisserie',
            'telephone' => 'Téléphone',
            'autres' => 'Autres',
        ];
    }
}