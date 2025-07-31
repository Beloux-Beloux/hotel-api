<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    /**
     * Verify user email (web route for backward compatibility)
     */
    public function verify(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            return view('emails.verification-failed', [
                'message' => 'Token de vérification manquant.'
            ]);
        }

        try {
            // Decode the token
            $decoded = base64_decode($token);
            list($userId, $emailHash, $expiry) = explode('|', $decoded);

            // Check if expired
            if (time() > $expiry) {
                return view('emails.verification-failed', [
                    'message' => 'Le lien de vérification a expiré.'
                ]);
            }

            // Find the user
            $user = User::findOrFail($userId);

            // Check if the hash matches
            if ($emailHash !== sha1($user->email)) {
                return view('emails.verification-failed', [
                    'message' => 'Le lien de vérification est invalide.'
                ]);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return view('emails.verification-success', [
                    'message' => 'Votre email a déjà été vérifié.',
                    'already_verified' => true
                ]);
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            return view('emails.verification-success', [
                'message' => 'Votre email a été vérifié avec succès !',
                'already_verified' => false
            ]);
        } catch (\Exception $e) {
            return view('emails.verification-failed', [
                'message' => 'Le lien de vérification est invalide.'
            ]);
        }
    }

    /**
     * Verify user email via API
     */
    public function verifyApi(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->input('token');

        try {
            // Decode the token
            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de vérification invalide.'
                ], 400);
            }

            list($userId, $emailHash, $expiry) = $parts;

            // Check if expired
            if (time() > $expiry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le lien de vérification a expiré.'
                ], 400);
            }

            // Find the user
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé.'
                ], 404);
            }

            // Check if the hash matches
            if ($emailHash !== sha1($user->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de vérification invalide.'
                ], 400);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                // Load user relationships
                $user->load(['roles.permissions', 'hotels']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Votre email a déjà été vérifié.',
                    'already_verified' => true,
                    'user' => $user,
                    'hotels' => $user->hotels
                ]);
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            // Load user relationships
            $user->load(['roles.permissions', 'hotels']);

            // Create auth token for auto-login with abilities based on permissions
            $abilities = [];
            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $abilities[] = $permission->name;
                }
            }
            
            $token = $user->createToken('auth-token', array_unique($abilities))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Votre email a été vérifié avec succès !',
                'already_verified' => false,
                'user' => $user,
                'token' => $token,
                'hotels' => $user->hotels,
                'abilities' => array_unique($abilities)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification.'
            ], 500);
        }
    }
}
