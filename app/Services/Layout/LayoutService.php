<?php // v0.4.125
/* app/Services/Layout/LayoutService.php
Purpose: Сборка $layout для строгого Blade: site/nav/title/locale, ссылки и флаги
         видимости пунктов навигации. Контроллеры передают только overrides.
FIX: Универсальная подсветка активного раздела без ошибок: если nav_active не
     задан, извлекаем первую буквенную секцию пути регуляркой ^/([a-zA-Z]+)
     из Request::path() с безопасным фолбеком на $_SERVER['REQUEST_URI'].
*/
namespace App\Services\Layout;

use Faravel\Http\Request;
use App\Http\ViewModels\Layout\LayoutVM;
use Faravel\Support\Facades\Auth;

final class LayoutService
{
    /**
     * Build LayoutVM for current request.
     *
     * @param Request             $request   Current request.
     * @param array<string,mixed> $overrides Optional overrides (title, site, nav_active).
     * @return LayoutVM
     */
    public function build(Request $request, array $overrides = []): LayoutVM
    {
        /** @var array<string,mixed>|null $usr */
        $usr = Auth::user();
        $isAuth  = is_array($usr);
        // Faravel role levels: 6+ admin; 3..5 moderator/dev
        $roleId  = $isAuth ? (int)($usr['role_id'] ?? 0) : 0;
        $isAdmin = $isAuth && $roleId >= 6;
        $isModer = $isAuth && $roleId >= 3 && $roleId < 6;

        // Title
        $title = isset($overrides['title']) ? (string)$overrides['title'] : 'Faravel';

        // site.* (stable defaults, overridable)
        $so = (array)($overrides['site'] ?? []);
        $site = [
            'title' => isset($so['title']) ? (string)$so['title'] : 'FARAVEL',
            'logo'  => [
                'url' => isset($so['logo']['url'])
                    ? (string)$so['logo']['url']
                    : '/style/logo.png',
            ],
            'home'  => [
                'url' => isset($so['home']['url'])
                    ? (string)$so['home']['url']
                    : '/',
            ],
        ];

        // Navigation active: override > first alpha path segment > 'home'
        $navActive = isset($overrides['nav_active']) && is_string($overrides['nav_active'])
            ? (string)$overrides['nav_active']
            : $this->detectActiveByPath($request);

        // Base links
        $links = [
            'home'     => '/',
            'forum'    => '/forum/',
            'login'    => '/login',
            'register' => '/register',
        ];

        // Admin/Mod links by role; logout for authenticated users
        if ($isAdmin) {
            $links['admin'] = '/admin';
        }
        if ($isModer || $isAdmin) {
            $links['mod'] = '/mod';
        }
        if ($isAuth) {
            $links['logout'] = '/logout';
        }

        // Build VM (shared CSRF for all page forms)
        return LayoutVM::fromArray([
            'locale' => 'ru',
            'title'  => $title,
            'csrf'   => (string) csrf_token(),
            'site'   => $site,
            'nav'    => [
                'active' => $navActive,
                'links'  => $links,
                'show'   => [
                    'admin' => $isAdmin,
                    'mod'   => ($isModer || $isAdmin),
                ],
                'auth'   => [
                    'is_auth'  => $isAuth,
                    'is_admin' => $isAdmin,
                    // Safe subset for header widgets.
                    'username' => $isAuth ? (string)($usr['username'] ?? '') : '',
                ],
            ],
        ]);
    }

    /**
     * Detect active section by first alpha path segment.
     *
     * @param Request $request
     * @return string 'admin'|'forum'|'home' etc.
     *
     * @example /admin/x -> admin; /forum/y -> forum; / -> home
     */
    private function detectActiveByPath(Request $request): string
    {
        // Try Request::path() if available.
        $path = '';
        if (method_exists($request, 'path')) {
            /** @var mixed $p */
            $p = $request->path();
            $path = is_string($p) ? $p : '';
        }

        // Fallback to server URI.
        if ($path === '') {
            $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
            $path = explode('?', $uri, 2)[0];
        }

        // Normalize to leading slash
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        // Only letters [a-zA-Z] are considered a section key.
        if (preg_match('~^/([a-zA-Z]+)~', $path, $m) === 1) {
            return strtolower($m[1]);
        }
        return 'home';
    }
}
