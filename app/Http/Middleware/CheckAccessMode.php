<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccessMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accessType = $request->header('X-Access-Type', 'local');

        if (!$user) {
            return $next($request);
        }

        if ($accessType === 'internet' && !$user->canAccessFromInternet()) {
            return response()->json([
                'message' => 'Accès non autorisé depuis Internet.'
            ], 403);
        }

        if ($accessType === 'local' && !$user->canAccessFromLocal()) {
            return response()->json([
                'message' => 'Accès non autorisé en local.'
            ], 403);
        }

        // If internet access, limit to read-only operations
        if ($accessType === 'internet' && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return response()->json([
                'message' => 'Seules les opérations de lecture sont autorisées depuis Internet.'
            ], 403);
        }

        return $next($request);
    }
}