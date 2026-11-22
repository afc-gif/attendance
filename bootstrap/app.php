<?php

use App\Http\Middleware\AdminConsoleAccess;
use App\Http\Middleware\EnsureStaffPasskeySession;
use App\Http\Middleware\HrAccessOnly;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'hr_access' => HrAccessOnly::class,
            'admin.console' => AdminConsoleAccess::class,
            'staff.passkey' => EnsureStaffPasskeySession::class,
        ]);
        $rawTrustedProxies = env('TRUSTED_PROXIES', '127.0.0.1,::1');
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            preg_split('/[\s,]+/', (string) $rawTrustedProxies)
        ), static fn (string $proxy): bool => $proxy !== '' && $proxy !== '*'));

        if (empty($trustedProxies)) {
            $trustedProxies = ['127.0.0.1', '::1'];
        }

        $middleware->trustProxies(
            at: $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson() || $request->is('api/*'),
        );
    })->create();
