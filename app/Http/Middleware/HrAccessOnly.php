<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HrAccessOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->isSuperAdmin() && ! $user->isHr())) {
            abort(403, 'Unauthorized. HR Management is restricted to HR and Super Admin.');
        }

        return $next($request);
    }
}
