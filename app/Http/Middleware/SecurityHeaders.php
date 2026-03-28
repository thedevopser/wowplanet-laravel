<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; "
                ."script-src 'self' https://umami.wowplanet.fr https://wow.zamimg.com; "
                ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                ."font-src 'self' https://fonts.gstatic.com; "
                ."img-src 'self' https://wow.zamimg.com https://render.worldofwarcraft.com data:; "
                ."connect-src 'self' https://umami.wowplanet.fr https://nether.wowhead.com; "
                ."frame-ancestors 'none';"
            );
        }

        return $response;
    }
}
