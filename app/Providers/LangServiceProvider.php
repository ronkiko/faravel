<?php

namespace App\Providers;

use App\Support\Lang;
use Faravel\Foundation\ServiceProvider;

use App\Support\Logger;

class LangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Debug: provider register
        Logger::log('PROVIDER.REGISTER', static::class . ' register');

        $this->app->singleton('lang', function ($app) {
            // defaults можно взять из SettingsService позже; пока — статично
            $locale   = \App\Services\SettingsService::get('app.locale', 'ru') ?: 'ru';
            $fallback = \App\Services\SettingsService::get('app.fallback_locale', 'en') ?: 'en';
            $paths    = [base_path('resources/lang')];
            return new Lang($locale, $fallback, $paths);
        });
    }
}
