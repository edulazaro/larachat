<?php

namespace EduLazaro\Larachat\Http\Middleware;

use Closure;
use EduLazaro\Larachat\Larachat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel answers 404 to anyone who is not a logged-in admin, instead of
 * redirecting to the login: only someone who knows the login path learns
 * that there is one.
 */
class PanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Larachat::isAdmin($request->user()), 404);

        return $next($request);
    }
}
