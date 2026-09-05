<?php

namespace App\Http\Middleware;

use Closure;

class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if($request->is('api/public/clinic','api/public/content','api/public/services'))$response->headers->set('Cache-Control','no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        if ($request->is('api/admin/*', 'api/auth/*', 'api/public/appointments*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
