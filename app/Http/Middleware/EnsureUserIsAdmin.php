<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the Node app's `protect + requireRole('admin')` pair.
 * Session auth is handled by Laravel's own `auth` middleware; this only checks
 * the role.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Please sign in to continue.'], 401)
                : redirect()->route('login');
        }

        if (! $user->isAdmin()) {
            abort(403, 'This action requires the role: admin.');
        }

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
