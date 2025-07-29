<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin user without hotel_id first
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@hotel.com',
            'access_mode' => 'both',
        ]);
        $admin->roles()->attach(\App\Models\Role::where('name', 'admin')->first());

        // Create hotel (will attach the admin user)
        $this->call(HotelSeeder::class);

        // Get hotel ID from config
        $hotelId = config('seeding.hotel_id');

        // Create test users for each role (without hotel_id in users table)
        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@hotel.com',
            'access_mode' => 'both',
        ]);
        $manager->roles()->attach(\App\Models\Role::where('name', 'manager')->first());
        $manager->hotels()->attach($hotelId, ['role' => 'manager', 'created_at' => now(), 'updated_at' => now()]);

        $receptionist = User::factory()->create([
            'name' => 'Receptionist User',
            'email' => 'receptionist@hotel.com',
            'access_mode' => 'local',
        ]);
        $receptionist->roles()->attach(\App\Models\Role::where('name', 'receptionist')->first());
        $receptionist->hotels()->attach($hotelId, ['role' => 'staff', 'created_at' => now(), 'updated_at' => now()]);

        // Seed hotel data
        $this->call([
            RoomTypeSeeder::class,
            RoomSeeder::class,
            GuestSeeder::class,
            ReservationSeeder::class,
        ]);
    }
}
