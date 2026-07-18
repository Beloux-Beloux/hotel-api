<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Folio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefaultHotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create default hotel
            $hotel = Hotel::create([
                'name' => 'Grand Hotel Paradise',
                'slug' => 'grand-hotel-paradise',
                'address' => [
                    'street' => '123 Avenue des Champs',
                    'city' => 'Paris',
                    'postal_code' => '75008',
                    'country' => 'France',
                ],
                'contact_info' => [
                    'phone' => '+33 1 23 45 67 89',
                    'email' => 'contact@grandhotelparadise.com',
                    'website' => 'https://grandhotelparadise.com',
                ],
                'settings' => [
                    'check_in_time' => '14:00',
                    'check_out_time' => '12:00',
                    'children_age_limit' => 12,
                    'breakfast_included' => true,
                ],
                'currency' => 'EUR',
                'timezone' => 'Europe/Paris',
                'is_active' => true,
            ]);

            // Update all existing data to belong to this hotel
            $hotelId = $hotel->id;

            // Update users - assign them to the hotel
            $users = User::all();
            foreach ($users as $user) {
                // Set current hotel
                $user->update(['current_hotel_id' => $hotelId]);
                
                // Attach user to hotel with appropriate role
                $role = 'staff';
                if ($user->hasRole('admin')) {
                    $role = 'manager';
                }
                
                $user->hotels()->attach($hotelId, [
                    'role' => $role,
                    'is_default' => true,
                ]);
            }

            // Update all other tables with hotel_id
            RoomType::whereNull('hotel_id')->update(['hotel_id' => $hotelId]);
            Room::whereNull('hotel_id')->update(['hotel_id' => $hotelId]);
            Guest::whereNull('hotel_id')->update(['hotel_id' => $hotelId]);
            Reservation::whereNull('hotel_id')->update(['hotel_id' => $hotelId]);
            Folio::whereNull('hotel_id')->update(['hotel_id' => $hotelId]);

            $this->command->info('Default hotel created and all data migrated successfully!');
        });
    }
}