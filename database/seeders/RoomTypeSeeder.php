<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotelId = config('seeding.hotel_id');
        
        if (!$hotelId) {
            $this->command->error('No hotel ID found. Please run HotelSeeder first.');
            return;
        }

        $roomTypes = [
            [
                'name' => 'Chambre Standard',
                'code' => 'STD',
                'base_price' => 75.00,
                'max_occupancy' => 2,
                'description' => 'Chambre confortable avec lit double ou deux lits simples',
                'amenities' => ['wifi', 'tv', 'climatisation', 'salle_de_bain_privee', 'telephone'],
            ],
            [
                'name' => 'Chambre Supérieure',
                'code' => 'SUP',
                'base_price' => 95.00,
                'max_occupancy' => 2,
                'description' => 'Chambre spacieuse avec vue sur le jardin',
                'amenities' => ['wifi', 'tv', 'climatisation', 'salle_de_bain_privee', 'telephone', 'minibar', 'coffre_fort', 'balcon'],
            ],
            [
                'name' => 'Chambre Deluxe',
                'code' => 'DLX',
                'base_price' => 125.00,
                'max_occupancy' => 3,
                'description' => 'Chambre luxueuse avec coin salon',
                'amenities' => ['wifi', 'tv', 'climatisation', 'salle_de_bain_privee', 'telephone', 'minibar', 'coffre_fort', 'balcon', 'coin_salon', 'machine_cafe'],
            ],
            [
                'name' => 'Suite Junior',
                'code' => 'JSU',
                'base_price' => 175.00,
                'max_occupancy' => 3,
                'description' => 'Suite avec chambre et salon séparés',
                'amenities' => ['wifi', 'tv', 'climatisation', 'salle_de_bain_privee', 'telephone', 'minibar', 'coffre_fort', 'balcon', 'salon_separe', 'machine_cafe', 'baignoire'],
            ],
            [
                'name' => 'Suite Présidentielle',
                'code' => 'PSU',
                'base_price' => 350.00,
                'max_occupancy' => 4,
                'description' => 'Suite de luxe avec deux chambres et services exclusifs',
                'amenities' => ['wifi', 'tv', 'climatisation', 'salle_de_bain_privee', 'telephone', 'minibar', 'coffre_fort', 'terrasse', 'salon_separe', 'machine_cafe', 'jacuzzi', 'cuisine', 'bureau'],
            ],
        ];

        foreach ($roomTypes as $roomType) {
            $roomType['hotel_id'] = $hotelId;
            RoomType::create($roomType);
        }
    }
}