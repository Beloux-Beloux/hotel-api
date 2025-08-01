<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use App\Mail\HotelInvitation;
use App\Mail\StaffAccountCreated;
use Illuminate\Support\Facades\Log;

class HotelUserController extends Controller
{
    /**
     * Get all users for a specific hotel
     */
    public function index(Hotel $hotel)
    {
        // Verify user has access to this hotel
        if (!auth()->user()->hotels->contains($hotel)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = $hotel->users()->withPivot('role', 'created_at')->get();

        return response()->json($users);
    }

    /**
     * Invite a user to a hotel
     */
    public function invite(Request $request, Hotel $hotel)
    {
        // Only owners, admins and managers can invite users
        $userRole = auth()->user()->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        if (!in_array($userRole, ['owner', 'admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,manager,receptionist,housekeeper,housekeeping_staff,staff'
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Check if user already has access to this hotel
            if ($user->hotels->contains($hotel)) {
                return response()->json(['message' => 'L\'utilisateur a déjà accès à cet hôtel'], 409);
            }

            // Add user to hotel
            $hotel->users()->attach($user->id, [
                'role' => $request->role,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // TODO: Send notification email
        } else {
            // TODO: Create invitation and send email
            // For now, return message that user needs to register first
            return response()->json([
                'message' => 'L\'utilisateur doit d\'abord créer un compte'
            ], 404);
        }

        return response()->json(['message' => 'Utilisateur ajouté avec succès']);
    }

    /**
     * Update a user's role in a hotel
     */
    public function updateRole(Request $request, Hotel $hotel, User $user)
    {
        // Only owners and admins can update roles
        $currentUserRole = auth()->user()->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        if (!in_array($currentUserRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'role' => 'required|in:admin,manager,receptionist,housekeeper,housekeeping_staff,staff'
        ]);

        // Prevent changing to owner role
        if ($request->role === 'owner') {
            return response()->json(['message' => 'Le rôle propriétaire ne peut pas être assigné manuellement'], 403);
        }

        // Check if user belongs to hotel
        if (!$user->hotels->contains($hotel)) {
            return response()->json(['message' => 'Utilisateur non trouvé dans cet hôtel'], 404);
        }

        // Get the user's current role in this hotel
        $userCurrentRole = $user->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        
        // Prevent changing the role of an owner
        if ($userCurrentRole === 'owner') {
            return response()->json(['message' => 'Le rôle d\'un propriétaire ne peut pas être modifié'], 403);
        }

        // Update role
        $hotel->users()->updateExistingPivot($user->id, [
            'role' => $request->role,
            'updated_at' => now()
        ]);

        return response()->json(['message' => 'Rôle mis à jour avec succès']);
    }

    /**
     * Remove a user from a hotel
     */
    public function remove(Hotel $hotel, User $user)
    {
        // Only owners and admins can remove users
        $currentUserRole = auth()->user()->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        if (!in_array($currentUserRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cannot remove yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Vous ne pouvez pas vous retirer vous-même'], 400);
        }

        // Check if user belongs to hotel
        if (!$user->hotels->contains($hotel)) {
            return response()->json(['message' => 'Utilisateur non trouvé dans cet hôtel'], 404);
        }

        // Get the user's role in this hotel
        $userRole = $user->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        
        // Prevent removing an owner
        if ($userRole === 'owner') {
            return response()->json(['message' => 'Un propriétaire ne peut pas être retiré de l\'hôtel'], 403);
        }

        // Remove user from hotel
        $hotel->users()->detach($user->id);

        return response()->json(['message' => 'Utilisateur retiré avec succès']);
    }

    /**
     * Get current user's permissions in the hotel
     */
    public function currentUserPermissions(Hotel $hotel)
    {
        $user = auth()->user();

        // Get user's role in this hotel
        $hotelUser = $user->hotels()->where('hotels.id', $hotel->id)->first();
        if (!$hotelUser) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role = $hotelUser->pivot->role;

        // Define permissions based on role
        $permissions = [];
        switch ($role) {
            case 'owner':
                $permissions = ['*']; // All permissions
                break;
            case 'admin':
                $permissions = ['*']; // All permissions
                break;
            case 'manager':
                $permissions = [
                    'reservations.*',
                    'rooms.*',
                    'guests.*',
                    'reports.view',
                    'settings.view'
                ];
                break;
            case 'receptionist':
                $permissions = [
                    'reservations.*',
                    'rooms.view',
                    'rooms.update_status',
                    'guests.*'
                ];
                break;
            case 'housekeeper':
                $permissions = [
                    'rooms.view',
                    'rooms.update_status',
                    'housekeeping.*'
                ];
                break;
            case 'housekeeping_staff':
                $permissions = [
                    'rooms.view',
                    'housekeeping.tasks.view',
                    'housekeeping.tasks.update'
                ];
                break;
            case 'staff':
                $permissions = [
                    'rooms.view',
                    'reservations.view',
                    'guests.view'
                ];
                break;
        }

        return response()->json($permissions);
    }

    /**
     * Create a staff account with direct password
     */
    public function createStaff(Request $request, Hotel $hotel)
    {
        // Only owners, admins and managers can create staff accounts
        $userRole = auth()->user()->hotels()->where('hotels.id', $hotel->id)->first()->pivot->role;
        if (!in_array($userRole, ['owner', 'admin', 'manager'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,manager,receptionist,housekeeper,housekeeping_staff,staff'
        ]);

        DB::beginTransaction();
        try {
            // Create the user with email verification status based on mail configuration
            $emailVerified = !config('mail.default') || config('mail.default') === 'log';

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'email_verified_at' => $emailVerified ? now() : null
            ]);

            // Attach user to hotel with specified role
            $hotel->users()->attach($user->id, [
                'role' => $request->role,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $emailSent = false;

            // Only send email if mail is configured
            if (!$emailVerified && config('mail.default') && config('mail.default') !== 'log') {
                try {
                    // Generate a simple verification token
                    $verificationToken = base64_encode($user->id . '|' . sha1($user->email) . '|' . Carbon::now()->addHours(24)->timestamp);

                    // Generate verification URL pointing to frontend
                    $verificationUrl = config('app.frontend_url') . '/verify-email?token=' . $verificationToken;

                    // Send verification email with temporary password
                    Mail::to($user->email)->send(new StaffAccountCreated(
                        $user,
                        $hotel,
                        $request->password,
                        $verificationUrl
                    ));

                    $emailSent = true;
                } catch (\Exception $e) {
                    error_log('Failed to send verification email: ' . $e->getMessage());
                    // Continue without email
                }
            }

            DB::commit();

            // Return user with pivot data and verification status
            $user->pivot = (object) [
                'role' => $request->role,
                'created_at' => now()->toISOString()
            ];

            $message = $emailSent
                ? 'Compte créé avec succès. Un email de vérification a été envoyé.'
                : 'Compte créé avec succès.';

            return response()->json([
                'user' => $user,
                'message' => $message,
                'email_sent' => $emailSent
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du compte',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
