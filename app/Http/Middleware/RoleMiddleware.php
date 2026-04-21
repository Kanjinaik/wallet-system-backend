<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', (string) $role))
            ->map(fn ($role) => trim((string) $role))
            ->filter()
            ->values()
            ->all();

        $actualRole = $request->user()->role === 'user' ? 'retailer' : $request->user()->role;
        $normalizedAllowed = array_map(function ($r) {
            return $r === 'user' ? 'retailer' : $r;
        }, $allowedRoles);

        if (!in_array($actualRole, $normalizedAllowed, true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
