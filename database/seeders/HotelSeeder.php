<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo hotel
        $hotel = Hotel::create([
            'name' => 'Grand Hotel Demo',
            'slug' => 'grand-hotel-demo',
            'address' => [
                'street' => '123 Avenue Principale',
                'city' => 'Paris',
                'postal_code' => '75001',
                'country' => 'France'
            ],
            'contact_info' => [
                'phone' => '+33 1 23 45 67 89',
                'email' => 'contact@grandhoteldemo.com',
                'website' => 'https://grandhoteldemo.com'
            ],
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'settings' => [
                'check_in_time' => '14:00',
                'check_out_time' => '11:00',
                'cancellation_policy' => '24 hours',
                'languages' => ['fr', 'en'],
                'payment_methods' => ['cash', 'card', 'bank_transfer']
            ],
            'is_active' => true,
            'subscription_plan' => 'premium',
            'subscription_expires_at' => now()->addYear()
        ]);

        // Attach the admin user to this hotel (without updating hotel_id since it's in a separate table)
        $admin = User::where('email', 'admin@hotel.com')->first();
        if ($admin) {
            // Only attach to the pivot table
            $admin->hotels()->attach($hotel->id, [
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create a second hotel for testing
        $hotel2 = Hotel::create([
            'name' => 'Hotel Luxe Paris',
            'slug' => 'hotel-luxe-paris',
            'address' => [
                'street' => '456 Boulevard Saint-Germain',
                'city' => 'Paris',
                'postal_code' => '75006',
                'country' => 'France'
            ],
            'contact_info' => [
                'phone' => '+33 1 98 76 54 32',
                'email' => 'contact@hotelluxeparis.com',
                'website' => 'https://hotelluxeparis.com'
            ],
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'settings' => [
                'check_in_time' => '15:00',
                'check_out_time' => '12:00',
                'cancellation_policy' => '48 hours',
                'languages' => ['fr', 'en', 'es'],
                'payment_methods' => ['cash', 'card', 'bank_transfer', 'paypal']
            ],
            'is_active' => true,
            'subscription_plan' => 'standard',
            'subscription_expires_at' => now()->addMonths(6)
        ]);

        // Attach admin to second hotel as manager
        if ($admin) {
            $admin->hotels()->attach($hotel2->id, [
                'role' => 'manager',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Store the hotel ID for use in other seeders
        config(['seeding.hotel_id' => $hotel->id]);
    }
}