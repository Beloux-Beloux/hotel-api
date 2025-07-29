<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToHotel;

class Guest extends Model
{
    use HasFactory, BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'id_type',
        'id_number',
        'nationality',
        'address',
        'preferences',
        'vip_status',
    ];

    protected $casts = [
        'address' => 'array',
        'preferences' => 'array',
        'vip_status' => 'boolean',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getTotalReservationsAttribute(): int
    {
        return $this->reservations()->count();
    }

    public function getLastVisitAttribute()
    {
        return $this->reservations()
            ->where('status', 'terminee')
            ->latest('check_out_date')
            ->value('check_out_date');
    }

    public function getTotalSpentAttribute(): float
    {
        return $this->folios()
            ->where('status', 'ferme')
            ->sum('total_amount');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%");
        });
    }

    public function scopeVip($query)
    {
        return $query->where('vip_status', true);
    }
}