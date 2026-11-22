<?php

namespace App\Providers;

use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passkeys::ignoreRoutes();
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if (
            $this->app->environment('production') ||
            request()->header('x-forwarded-proto') === 'https' ||
            str_starts_with((string) config('app.url'), 'https://')
        ) {
            URL::forceScheme('https');
        }

        // Dynamically apply the timezone stored in AttendanceSetting (e.g. Africa/Lagos)
        // so that ALL Carbon::now(), Carbon::today() calls throughout the app use the
        // correct local time — both for late-arrival evaluation AND for display.
        $this->applyTimezoneFromSettings();
    }

    protected function applyTimezoneFromSettings(): void
    {
        try {
            $tz = AttendanceSetting::get('timezone', config('app.timezone', 'UTC'));

            if (! empty($tz) && in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
                Carbon::setTestNow(null); // clear any test mock
            }
        } catch (\Throwable $e) {
            // During migrations or before DB is ready, silently fall back
        }
    }
}
