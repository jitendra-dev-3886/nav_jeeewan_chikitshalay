<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user()?->active && in_array($request->user()->role, $roles), 403, 'You do not have permission for this action.');

        return $next($request);
    }
}
