<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'is_super_admin',
        'access_mode',
        'last_login_at',
        'current_hotel_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }
        return false;
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function canAccessFromInternet(): bool
    {
        return in_array($this->access_mode, ['internet', 'both']);
    }

    public function canAccessFromLocal(): bool
    {
        return in_array($this->access_mode, ['local', 'both']);
    }

    /**
     * Get the hotels the user belongs to.
     */
    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'user_hotels')
            ->withPivot('role', 'is_default')
            ->withTimestamps();
    }

    /**
     * Get the current hotel.
     */
    public function currentHotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'current_hotel_id');
    }

    /**
     * Switch to a different hotel.
     */
    public function switchHotel(Hotel $hotel): bool
    {
        if ($this->hotels->contains($hotel)) {
            $this->update(['current_hotel_id' => $hotel->id]);
            return true;
        }
        return false;
    }

    /**
     * Check if user has access to a specific hotel.
     */
    public function hasAccessToHotel(Hotel $hotel): bool
    {
        return $this->is_super_admin || $this->hotels->contains($hotel);
    }

    /**
     * Get user's role in a specific hotel.
     */
    public function getRoleInHotel(Hotel $hotel): ?string
    {
        $userHotel = $this->hotels()->where('hotel_id', $hotel->id)->first();
        return $userHotel ? $userHotel->pivot->role : null;
    }
}
