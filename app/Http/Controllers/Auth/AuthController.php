<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'access_type' => 'required|in:local,internet',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les informations d\'identification fournies sont incorrectes.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Votre compte a été désactivé.'],
            ]);
        }

        // Check access mode
        if ($request->access_type === 'internet' && !$user->canAccessFromInternet()) {
            throw ValidationException::withMessages([
                'access' => ['Vous n\'êtes pas autorisé à accéder depuis Internet.'],
            ]);
        }

        if ($request->access_type === 'local' && !$user->canAccessFromLocal()) {
            throw ValidationException::withMessages([
                'access' => ['Vous n\'êtes pas autorisé à accéder en local.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token with abilities based on permissions
        $abilities = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $abilities[] = $permission->name;
            }
        }

        $token = $user->createToken('auth-token', array_unique($abilities))->plainTextToken;

        return response()->json([
            'user' => $user->load('roles.permissions'),
            'token' => $token,
            'abilities' => array_unique($abilities),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user()->load('roles.permissions'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Lien de réinitialisation envoyé']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès']);
    }
}