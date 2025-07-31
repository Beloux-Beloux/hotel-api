<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToHotel;

class CleaningChecklist extends Model
{
    use HasFactory, HasUuids, BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'name',
        'items',
        'estimated_minutes',
        'active'
    ];

    protected $casts = [
        'items' => 'array',
        'estimated_minutes' => 'integer',
        'active' => 'boolean'
    ];

    protected $attributes = [
        'active' => true,
        'estimated_minutes' => 30
    ];

    protected static function booted()
    {
        static::creating(function ($checklist) {
            if (is_null($checklist->items)) {
                $checklist->items = self::getDefaultItems();
            }
        });
    }

    /**
     * Get the room type for this checklist.
     */
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Get default checklist items.
     */
    public static function getDefaultItems()
    {
        return [
            ['id' => 'bed', 'label' => 'Faire le lit', 'required' => true],
            ['id' => 'bathroom', 'label' => 'Nettoyer la salle de bain', 'required' => true],
            ['id' => 'floor', 'label' => 'Aspirer/Nettoyer le sol', 'required' => true],
            ['id' => 'dust', 'label' => 'Dépoussiérer les meubles', 'required' => true],
            ['id' => 'trash', 'label' => 'Vider les poubelles', 'required' => true],
            ['id' => 'amenities', 'label' => 'Vérifier/Remplacer les équipements', 'required' => true],
            ['id' => 'minibar', 'label' => 'Vérifier le minibar', 'required' => false],
            ['id' => 'windows', 'label' => 'Nettoyer les vitres', 'required' => false],
            ['id' => 'balcony', 'label' => 'Nettoyer le balcon/terrasse', 'required' => false],
        ];
    }
}