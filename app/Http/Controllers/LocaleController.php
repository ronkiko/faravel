<?php

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;

class LocaleController
{
    public function switch(Request $request, string $locale): Response
    {
        $csv = (string) \App\Services\SettingsService::get('app.locales', 'ru,en');
        $allowed = array_values(array_filter(array_map('trim', explode(',', $csv))));
        $locale = strtolower(preg_replace('/[^a-z0-9_-]/','', $locale) ?: 'ru');

        if (!in_array($locale, $allowed, true)) {
            return response('Unsupported locale', 400);
        }
        $request->session()->put('_locale', $locale);

        // вернёмся туда, откуда пришли, либо на главную
        $back = (string)$request->server('HTTP_REFERER') ?: '/';
        return redirect($back);
    }
}
