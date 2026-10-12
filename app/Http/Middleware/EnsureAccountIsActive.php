<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors the `is_active` check the Node `protect` middleware performed: a
 * deactivated account is signed out rather than merely blocked.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Contact your administrator.',
                ], 403);
            }

            return redirect()->route('login')->with(
                'error',
                'Your account has been deactivated. Contact your administrator.'
            );
        }

        return $next($request);
    }
}
