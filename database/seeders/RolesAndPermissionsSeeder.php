<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Roles
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Accès complet au système'
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Gestion de l\'hôtel et accès aux rapports'
            ],
            [
                'name' => 'receptionist',
                'display_name' => 'Réceptionniste',
                'description' => 'Gestion des réservations et check-in/out'
            ],
            [
                'name' => 'housekeeper',
                'display_name' => 'Gouvernante',
                'description' => 'Gestion du service de chambre'
            ],
            [
                'name' => 'maintenance',
                'display_name' => 'Maintenance',
                'description' => 'Gestion de la maintenance'
            ],
            [
                'name' => 'restaurant',
                'display_name' => 'Restaurant',
                'description' => 'Gestion du restaurant et des stocks'
            ]
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        // Create Permissions
        $permissions = [
            // Hébergement
            ['name' => 'reservations.view', 'display_name' => 'Voir les réservations', 'module' => 'hebergement'],
            ['name' => 'reservations.create', 'display_name' => 'Créer des réservations', 'module' => 'hebergement'],
            ['name' => 'reservations.edit', 'display_name' => 'Modifier les réservations', 'module' => 'hebergement'],
            ['name' => 'reservations.delete', 'display_name' => 'Supprimer les réservations', 'module' => 'hebergement'],
            
            ['name' => 'rooms.view', 'display_name' => 'Voir les chambres', 'module' => 'hebergement'],
            ['name' => 'rooms.edit', 'display_name' => 'Gérer les chambres et types', 'module' => 'hebergement'],
            ['name' => 'rooms.manage_states', 'display_name' => 'Gérer les états des chambres', 'module' => 'hebergement'],
            ['name' => 'rooms.housekeeping', 'display_name' => 'Gérer le service de chambre', 'module' => 'hebergement'],
            
            ['name' => 'billing.view', 'display_name' => 'Voir la facturation', 'module' => 'hebergement'],
            ['name' => 'billing.create', 'display_name' => 'Créer des factures', 'module' => 'hebergement'],
            ['name' => 'billing.edit', 'display_name' => 'Modifier les factures', 'module' => 'hebergement'],
            
            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'Voir le tableau de bord', 'module' => 'dashboard'],
            ['name' => 'reports.view', 'display_name' => 'Voir les rapports', 'module' => 'dashboard'],
            ['name' => 'reports.export', 'display_name' => 'Exporter les rapports', 'module' => 'dashboard'],
            
            // Restaurant
            ['name' => 'restaurant.orders', 'display_name' => 'Gérer les commandes', 'module' => 'restaurant'],
            ['name' => 'restaurant.stock', 'display_name' => 'Gérer les stocks', 'module' => 'restaurant'],
            ['name' => 'restaurant.recipes', 'display_name' => 'Gérer les fiches techniques', 'module' => 'restaurant'],
            
            // Débiteurs
            ['name' => 'debtors.view', 'display_name' => 'Voir les débiteurs', 'module' => 'debtors'],
            ['name' => 'debtors.manage', 'display_name' => 'Gérer les débiteurs', 'module' => 'debtors'],
            
            // Système
            ['name' => 'users.manage', 'display_name' => 'Gérer les utilisateurs', 'module' => 'system'],
            ['name' => 'settings.manage', 'display_name' => 'Gérer les paramètres', 'module' => 'system'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        $adminRole->permissions()->syncWithoutDetaching(Permission::all());

        $managerRole = Role::where('name', 'manager')->first();
        $managerRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'reservations.view', 'reservations.create', 'reservations.edit',
                'rooms.view', 'rooms.edit', 'rooms.manage_states',
                'billing.view', 'billing.create', 'billing.edit',
                'dashboard.view', 'reports.view', 'reports.export',
                'restaurant.orders', 'restaurant.stock',
                'debtors.view', 'debtors.manage'
            ])->get()
        );

        $receptionistRole = Role::where('name', 'receptionist')->first();
        $receptionistRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'reservations.view', 'reservations.create', 'reservations.edit',
                'rooms.view', 'rooms.manage_states',
                'billing.view', 'billing.create',
                'dashboard.view'
            ])->get()
        );

        $housekeeperRole = Role::where('name', 'housekeeper')->first();
        $housekeeperRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'rooms.view', 'rooms.housekeeping',
                'dashboard.view'
            ])->get()
        );

        $maintenanceRole = Role::where('name', 'maintenance')->first();
        $maintenanceRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'rooms.view', 'rooms.manage_states',
                'dashboard.view'
            ])->get()
        );

        $restaurantRole = Role::where('name', 'restaurant')->first();
        $restaurantRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'restaurant.orders', 'restaurant.stock', 'restaurant.recipes',
                'dashboard.view'
            ])->get()
        );
    }
}