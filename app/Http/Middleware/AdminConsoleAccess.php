<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminConsoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessAdminConsole()) {
            abort(403, 'Unauthorized. Restricted to administrators and HR.');
        }

        return $next($request);
    }
}
