<?php

namespace Database\Seeders;

use App\Models\Guest;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class GuestSeeder extends Seeder
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

        $faker = Faker::create('fr_FR');
        
        // Clients VIP
        for ($i = 0; $i < 5; $i++) {
            Guest::create([
                'hotel_id' => $hotelId,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'id_type' => 'passeport',
                'id_number' => strtoupper($faker->bothify('??######')),
                'nationality' => $faker->randomElement(['FR', 'US', 'GB', 'DE', 'IT', 'ES']),
                'address' => [
                    'street' => $faker->streetAddress,
                    'city' => $faker->city,
                    'postal_code' => $faker->postcode,
                    'country' => $faker->country,
                ],
                'preferences' => [
                    'room_floor' => $faker->randomElement(['high', 'low', null]),
                    'bed_type' => $faker->randomElement(['king', 'twin', null]),
                    'special_requests' => $faker->randomElement(['Oreiller supplémentaire', 'Chambre loin de l\'ascenseur', null]),
                ],
                'vip_status' => true,
            ]);
        }

        // Clients réguliers
        for ($i = 0; $i < 20; $i++) {
            Guest::create([
                'hotel_id' => $hotelId,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'id_type' => $faker->randomElement(['carte_identite', 'passeport', 'permis_conduire']),
                'id_number' => strtoupper($faker->bothify('??######')),
                'nationality' => $faker->randomElement(['FR', 'BE', 'CH', 'CA', 'US', 'GB']),
                'address' => [
                    'street' => $faker->streetAddress,
                    'city' => $faker->city,
                    'postal_code' => $faker->postcode,
                    'country' => $faker->country,
                ],
                'preferences' => $faker->randomElement([
                    ['room_floor' => 'high'],
                    ['bed_type' => 'king'],
                    ['smoking' => false],
                    null,
                ]),
                'vip_status' => false,
            ]);
        }

        // Quelques clients sans email pour tester
        for ($i = 0; $i < 5; $i++) {
            Guest::create([
                'hotel_id' => $hotelId,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => null,
                'phone' => $faker->phoneNumber,
                'id_type' => 'carte_identite',
                'id_number' => strtoupper($faker->bothify('??######')),
                'nationality' => 'FR',
                'address' => null,
                'preferences' => null,
                'vip_status' => false,
            ]);
        }
    }
}