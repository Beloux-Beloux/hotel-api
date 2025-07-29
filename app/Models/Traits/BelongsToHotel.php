<?php

namespace App\Models\Traits;

use App\Models\Hotel;
use App\Scopes\HotelScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToHotel
{
    /**
     * Boot the belongs to hotel trait for a model.
     *
     * @return void
     */
    protected static function bootBelongsToHotel()
    {
        // Add global scope to filter by hotel
        static::addGlobalScope(new HotelScope);

        // Automatically set hotel_id when creating
        static::creating(function ($model) {
            if (!$model->hotel_id && auth()->check()) {
                $model->hotel_id = auth()->user()->current_hotel_id;
            }
        });
    }

    /**
     * Get the hotel that owns the model.
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Scope a query to only include models for a specific hotel.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $hotelId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForHotel($query, $hotelId)
    {
        return $query->where($this->getTable() . '.hotel_id', $hotelId);
    }

    /**
     * Check if the model belongs to the current user's hotel.
     *
     * @return bool
     */
    public function belongsToCurrentHotel(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->hotel_id === auth()->user()->current_hotel_id;
    }
}