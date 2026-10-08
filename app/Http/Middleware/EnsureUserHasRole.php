<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Allow the request through only if the authenticated user's role matches
     * one of the given roles, e.g. `role:admin` or `role:admin,inspection_staff`.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return $this->deny($request, 'Your account is not active.', 403);
        }

        if (! in_array($user->role?->name, $roles, true)) {
            return $this->deny($request, 'You are not authorized to access this resource.', 403);
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['status' => 'error', 'message' => $message, 'data' => null], $status);
        }

        abort($status, $message);
    }
}
