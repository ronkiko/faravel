<?php // v0.4.124
/* app/Services/Layout/LayoutService.php
Purpose: Сборка $layout для строгого Blade: site/nav/title/locale, ссылки и флаги
         видимости пунктов навигации. Контроллеры передают только overrides.
FIX: Вынесен общий CSRF-токен на верхний уровень layout.csrf (один на страницу),
     чтобы любые формы могли использовать его без директив и хелперов в Blade.
     Источник пользователя — Auth::user(); legacy-ключи исключены.
*/
namespace App\Services\Layout;

use Faravel\Http\Request;
use App\Http\ViewModels\Layout\LayoutVM;
use Faravel\Support\Facades\Auth;

final class LayoutService
{
    /**
     * @param Request             $request   Текущий запрос (для унификации сигнатур).
     * @param array<string,mixed> $overrides Переопределения полей лейаута.
     * @return LayoutVM
     */
    public function build(Request $request, array $overrides = []): LayoutVM
    {
        // 1) Current user via Auth service (canonical source of truth)
        /** @var array<string,mixed>|null $usr */
        $usr = Auth::user();
        $isAuth  = is_array($usr);
        // Faravel role levels: 6+ admin; 3..5 moderator/dev
        $roleId  = $isAuth ? (int)($usr['role_id'] ?? 0) : 0;
        $isAdmin = $isAuth && $roleId >= 6;
        $isModer = $isAuth && $roleId >= 3 && $roleId < 6;

        // 2) Title
        $title = isset($overrides['title']) ? (string)$overrides['title'] : 'Faravel';

        // 3) site.* (stable defaults, overridable)
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

        // 4) Navigation (active is mandatory)
        $navActive = isset($overrides['nav_active'])
            ? (string)$overrides['nav_active']
            : 'home';

        // Base links always available
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

        // 5) Build VM (explicit flags + shared CSRF for all page forms)
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
                    // Keep username for header widgets (safe subset).
                    'username' => $isAuth ? (string)($usr['username'] ?? '') : '',
                ],
            ],
        ]);
    }
}
