<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'name',
        'items',
        'estimated_minutes',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Récupère le template par défaut pour un hôtel et type de chambre
     */
    public static function getDefaultForRoomType($hotelId, $roomTypeId)
    {
        return static::firstOrCreate(
            [
                'hotel_id' => $hotelId,
                'room_type_id' => $roomTypeId,
            ],
            [
                'name' => 'Checklist par défaut pour le type de chambre ' . $roomTypeId,
                'items' => static::getDefaultItems(),
                'estimated_minutes' => 30,
            ]
        );
    }

    public static function getDefaultItems(): array
    {
        return [
            [
                'id' => '1',
                'label' => 'Faire le lit',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '2',
                'label' => 'Nettoyer la salle de bain',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '3',
                'label' => 'Aspirer/Nettoyer le sol',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '4',
                'label' => 'Dépoussiérer les meubles',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '5',
                'label' => 'Vider les poubelles',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '6',
                'label' => 'Vérifier/Remplacer les équipements',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '7',
                'label' => 'Ranger la chambre',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '8',
                'label' => 'Nettoyer les vitres',
                'required' => false,
                'completed' => false
            ],
            [
                'id' => '9',
                'label' => 'Désinfecter les surfaces fréquemment touchées',
                'required' => true,
                'completed' => false
            ],
            [
                'id' => '10',
                'label' => 'Vérifier les articles de toilette',
                'required' => true,
                'completed' => false
            ],
        ];
    }
}