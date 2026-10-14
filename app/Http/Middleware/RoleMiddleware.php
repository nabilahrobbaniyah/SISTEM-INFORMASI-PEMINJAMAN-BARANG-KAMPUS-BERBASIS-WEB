<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && $user->is_active && in_array($user->role, $roles, true),
            403,
            'Anda tidak punya akses ke halaman ini.'
        );

        return $next($request);
    }
}
