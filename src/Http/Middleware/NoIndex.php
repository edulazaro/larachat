<?php

namespace EduLazaro\Larachat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the login and the panel out of search engines and caches. They are
 * not linked, not in robots.txt (that would reveal them) and not in the sitemap.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
