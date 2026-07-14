<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Expose a $moduleOn('key') helper to every view for module-aware UI.
        // The hospital relation is cached on the auth user, so this is a single query per request.
        View::composer('*', function ($view) {
            $enabled = auth()->user()?->hospital?->modules_enabled;
            // Tolerate a legacy/double-encoded value that reads back as a JSON string.
            if (is_string($enabled)) {
                $decoded = json_decode($enabled, true);
                $enabled = is_array($decoded) ? $decoded : [];
            }
            $view->with('moduleOn', fn (string $key) => empty($enabled) || ! is_array($enabled) || in_array($key, $enabled, true));
        });
    }
}
