<?php

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Http\Request;
use Faravel\Http\Response;

class SetLocale implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowed;

    public function __construct()
    {
        // Можно хранить список локалей в SettingsService: app.locales = "ru,en"
        $csv = (string) (\App\Services\SettingsService::get('app.locales', 'ru,en') ?? 'ru,en');
        $list = array_map('trim', explode(',', $csv));
        $this->allowed = array_values(array_filter($list, static fn($x) => $x !== ''));
        if (!$this->allowed) {
            $this->allowed = ['ru', 'en'];
        }
    }

    public function handle(Request $request, Closure $next): Response
    {
        // В Faravel\Request нет query(); используем input() с fallback на $_GET.
#        $lang = (string) ($request->input('lang') ?? ($_GET['lang'] ?? ''));
        $lang = can('lang.switch') ? (string)$request->input('lang', '') : '';

        $sess = $request->session();

        if ($lang !== '' && $this->isAllowed($lang)) {
            $sess->put('_locale', $lang);
        }

        $locale = (string) ($sess->get('_locale') ?? '')
            ?: (string) (\App\Services\SettingsService::get('app.locale', 'ru') ?? 'ru');

        if (!$this->isAllowed($locale)) {
            $locale = $this->allowed[0];
        }

        // Пытаемся выставить локаль в сервисе переводов, если он зарегистрирован
        try {
            /** @var \App\Support\Lang $translator */
            $translator = app('lang');
            if (is_object($translator) && method_exists($translator, 'setLocale')) {
                $translator->setLocale($locale);
            }
        } catch (\Throwable $e) {
            // Нет сервиса переводов — не критично
        }

        $resp = $next($request);
        $resp->setHeader('Content-Language', $locale);
        return $resp;
    }

    private function isAllowed(string $x): bool
    {
        $x = strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $x));
        return in_array($x, $this->allowed, true);
    }
}
