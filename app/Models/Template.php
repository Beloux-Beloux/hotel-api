<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToHotel;

class Template extends Model
{
    use HasFactory, HasUuids, BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'is_active'
    ];

    public function items()
    {
        return $this->hasMany(TemplateItem::class);
    }
}
