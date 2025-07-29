<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Models\Folio;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReservationSeeder extends Seeder
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

        $guests = Guest::where('hotel_id', $hotelId)->get();
        $rooms = Room::where('hotel_id', $hotelId)->where('status', 'libre_propre')->get();
        // Get users who have access to this hotel
        $users = User::whereHas('hotels', function ($query) use ($hotelId) {
            $query->where('hotels.id', $hotelId);
        })->get();

        // Réservations futures
        for ($i = 0; $i < 10; $i++) {
            $checkIn = Carbon::now()->addDays(rand(1, 30));
            $checkOut = $checkIn->copy()->addDays(rand(2, 7));
            $room = $rooms->random();

            Reservation::create([
                'hotel_id' => $hotelId,
                'guest_id' => $guests->random()->id,
                'room_id' => $room->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => rand(1, 2),
                'children' => rand(0, 2),
                'status' => 'confirmee',
                'room_rate' => $room->roomType->base_price,
                'total_amount' => $room->roomType->base_price * $checkIn->diffInDays($checkOut),
                'currency' => 'EUR',
                'deposit_amount' => rand(0, 1) ? rand(50, 200) : null,
                'special_requests' => rand(0, 1) ? 'Arrivée tardive prévue' : null,
                'source' => ['direct', 'booking', 'expedia', 'telephone'][rand(0, 3)],
                'created_by' => $users->random()->id,
            ]);
        }

        // Réservations en cours (clients actuellement à l'hôtel)
        $occupiedRooms = Room::where('hotel_id', $hotelId)->whereIn('number', ['105', '201', '204', '301'])->get();
        foreach ($occupiedRooms as $room) {
            $checkIn = Carbon::now()->subDays(rand(1, 3));
            $checkOut = Carbon::now()->addDays(rand(1, 4));
            
            $reservation = Reservation::create([
                'hotel_id' => $hotelId,
                'guest_id' => $guests->random()->id,
                'room_id' => $room->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => rand(1, 2),
                'children' => rand(0, 1),
                'status' => 'en_cours',
                'room_rate' => $room->roomType->base_price,
                'total_amount' => $room->roomType->base_price * $checkIn->diffInDays($checkOut),
                'currency' => 'EUR',
                'deposit_amount' => rand(100, 300),
                'source' => 'direct',
                'created_by' => $users->random()->id,
            ]);

            // Créer un folio pour les réservations en cours
            $folio = Folio::create([
                'hotel_id' => $hotelId,
                'reservation_id' => $reservation->id,
                'guest_id' => $reservation->guest_id,
                'opened_at' => $checkIn,
                'status' => 'ouvert',
                'currency' => 'EUR',
            ]);

            // Ajouter l'hébergement au folio
            $folio->addItem([
                'description' => 'Hébergement - ' . $room->roomType->name,
                'quantity' => $checkIn->diffInDays(Carbon::now()),
                'unit_price' => $room->roomType->base_price,
                'category' => 'hebergement',
                'date' => $checkIn,
            ]);

            // Ajouter quelques consommations aléatoires
            if (rand(0, 1)) {
                $folio->addItem([
                    'description' => 'Restaurant - Dîner',
                    'quantity' => 2,
                    'unit_price' => 35.00,
                    'category' => 'restaurant',
                    'date' => Carbon::now()->subDay(),
                ]);
            }

            $room->update(['status' => 'occupee_propre']);
        }

        // Réservations passées
        for ($i = 0; $i < 15; $i++) {
            $checkOut = Carbon::now()->subDays(rand(5, 60));
            $checkIn = $checkOut->copy()->subDays(rand(2, 5));
            
            Reservation::create([
                'hotel_id' => $hotelId,
                'guest_id' => $guests->random()->id,
                'room_id' => $rooms->random()->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => rand(1, 2),
                'children' => rand(0, 2),
                'status' => 'terminee',
                'room_rate' => rand(75, 200),
                'total_amount' => rand(150, 1000),
                'currency' => 'EUR',
                'source' => ['direct', 'booking', 'expedia'][rand(0, 2)],
                'created_by' => $users->random()->id,
            ]);
        }

        // Quelques annulations
        for ($i = 0; $i < 3; $i++) {
            $checkIn = Carbon::now()->addDays(rand(10, 40));
            $checkOut = $checkIn->copy()->addDays(rand(2, 4));
            
            Reservation::create([
                'hotel_id' => $hotelId,
                'guest_id' => $guests->random()->id,
                'room_id' => null,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => rand(1, 2),
                'children' => 0,
                'status' => 'annulee',
                'room_rate' => rand(75, 150),
                'total_amount' => rand(150, 600),
                'currency' => 'EUR',
                'source' => 'direct',
                'created_by' => $users->random()->id,
            ]);
        }
    }
}