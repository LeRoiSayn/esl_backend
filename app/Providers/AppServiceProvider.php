<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Apply the timezone stored in system_settings at runtime.
        // This affects Carbon::now(), date comparisons, and all datetime operations
        // without requiring a code change or container redeploy.
        try {
            $tz = \App\Models\SystemSetting::get('timezone', config('app.timezone', 'UTC'));
            if ($tz && $tz !== config('app.timezone')) {
                config(['app.timezone' => $tz]);
                date_default_timezone_set($tz);
                Carbon::setLocalTimezone(new \Carbon\CarbonTimeZone($tz));
            }
        } catch (\Throwable $_) {
            // DB might not be available during migrations — fail silently
        }
    }
}

