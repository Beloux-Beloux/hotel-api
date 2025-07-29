<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
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

        $roomTypes = RoomType::where('hotel_id', $hotelId)->get()->keyBy('code');
        
        // Étage 1
        for ($i = 1; $i <= 10; $i++) {
            Room::create([
                'hotel_id' => $hotelId,
                'number' => '10' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'room_type_id' => $roomTypes['STD']->id,
                'floor' => 1,
                'status' => 'libre_propre',
                'features' => ['vue_jardin' => $i % 2 == 0],
            ]);
        }

        // Étage 2
        for ($i = 1; $i <= 8; $i++) {
            Room::create([
                'hotel_id' => $hotelId,
                'number' => '20' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'room_type_id' => $i <= 4 ? $roomTypes['SUP']->id : $roomTypes['DLX']->id,
                'floor' => 2,
                'status' => 'libre_propre',
                'features' => ['vue_mer' => $i % 2 == 1],
            ]);
        }

        // Étage 3
        for ($i = 1; $i <= 6; $i++) {
            Room::create([
                'hotel_id' => $hotelId,
                'number' => '30' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'room_type_id' => $i <= 4 ? $roomTypes['DLX']->id : $roomTypes['JSU']->id,
                'floor' => 3,
                'status' => 'libre_propre',
                'features' => ['vue_mer' => true],
            ]);
        }

        // Étage 4 - Suites
        Room::create([
            'hotel_id' => $hotelId,
            'number' => '401',
            'room_type_id' => $roomTypes['JSU']->id,
            'floor' => 4,
            'status' => 'libre_propre',
            'features' => ['vue_panoramique' => true],
        ]);

        Room::create([
            'hotel_id' => $hotelId,
            'number' => '402',
            'room_type_id' => $roomTypes['JSU']->id,
            'floor' => 4,
            'status' => 'libre_propre',
            'features' => ['vue_panoramique' => true],
        ]);

        Room::create([
            'hotel_id' => $hotelId,
            'number' => '403',
            'room_type_id' => $roomTypes['PSU']->id,
            'floor' => 4,
            'status' => 'libre_propre',
            'features' => ['vue_panoramique' => true, 'terrasse_privee' => true],
        ]);

        // Quelques chambres avec différents statuts pour le test
        Room::where('hotel_id', $hotelId)->where('number', '105')->update(['status' => 'occupee_propre']);
        Room::where('hotel_id', $hotelId)->where('number', '203')->update(['status' => 'en_nettoyage']);
        Room::where('hotel_id', $hotelId)->where('number', '108')->update(['status' => 'hors_service', 'notes' => 'Réparation climatisation en cours']);
    }
}