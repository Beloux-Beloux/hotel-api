<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'address',
        'contact_info',
        'settings',
        'currency',
        'timezone',
        'is_active',
        'subscription_plan',
        'subscription_expires_at',
    ];

    protected $casts = [
        'address' => 'array',
        'contact_info' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'subscription_expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($hotel) {
            if (empty($hotel->slug)) {
                $hotel->slug = Str::slug($hotel->name);
            }
        });
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_hotels')
            ->withPivot('role', 'is_default')
            ->withTimestamps();
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_expires_at === null || 
               $this->subscription_expires_at->isFuture();
    }

    public function getFormattedAddress(): string
    {
        $address = $this->address;
        if (!$address) {
            return '';
        }

        $parts = [];
        if ($address['street'] ?? null) {
            $parts[] = $address['street'];
        }
        if ($address['city'] ?? null) {
            $parts[] = $address['city'];
        }
        if ($address['postal_code'] ?? null) {
            $parts[] = $address['postal_code'];
        }
        if ($address['country'] ?? null) {
            $parts[] = $address['country'];
        }

        return implode(', ', $parts);
    }
}