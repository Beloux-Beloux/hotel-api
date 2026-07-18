<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemplateItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'room_id',
        'staff_id',
        'day_of_week',
        'notes'
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function staff()
    {
        return $this->belongsTo(HousekeepingStaff::class);
    }
}
