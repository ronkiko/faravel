<?php // v0.4.4
/* app/Services/Layout/LayoutService.php
Purpose: Assemble LayoutVM (site/nav/locale/title) for thin controllers and
         strict Blade views.
FIX: Без функциональных изменений; комментарии и контракт уточнены под итоговый
     формат site.*. Алиас brand.* более не используется (удалён из VM).
*/
namespace App\Services\Layout;

use Faravel\Http\Request;
use App\Http\ViewModels\Layout\LayoutVM;

final class LayoutService
{
    /**
     * Build LayoutVM for any page.
     *
     * Preconditions:
     * - Request/session are available for auth detection.
     * - $overrides may include:
     *     title:string,
     *     nav_active:string,
     *     site: array{
     *       title?:string,
     *       logo?:array{url?:string},
     *       home?:array{url?:string}
     *     }
     *
     * Side effects: reads session keys to detect auth user (auth.user|authUser|user).
     *
     * @param Request $request
     * @param array<string,mixed> $overrides
     * @return LayoutVM
     * @throws \InvalidArgumentException On invalid override types.
     * @example
     *  $vm = $this->build($req, [
     *    'title'=>'Login',
     *    'nav_active'=>'login',
     *    'site'=>[
     *      'title'=>'FARAVEL',
     *      'logo'=>['url'=>'/style/logo.png'],
     *      'home'=>['url'=>'/']
     *    ]
     *  ]);
     */
    public function build(Request $request, array $overrides = []): LayoutVM
    {
        $s = $request->session();

        // 1) Detect user
        $usr = $s->get('auth.user');
        if (!is_array($usr)) {
            $u2  = $s->get('authUser');
            $usr = is_array($u2) ? $u2 : $s->get('user');
            if (!is_array($usr)) {
                $usr = null;
            }
        }
        $isAuth  = is_array($usr);
        $isAdmin = $isAuth && !empty($usr['is_admin']);

        // 2) Title
        $title = isset($overrides['title']) ? (string)$overrides['title'] : 'Faravel';

        // 3) site.* (stable defaults)
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

        if ($isAdmin) {
            $links['admin'] = '/admin';
        }
        if ($isAuth) {
            $links['logout'] = '/logout';
        }

        // 5) Build VM
        return LayoutVM::fromArray([
            'locale' => 'ru',
            'title'  => $title,
            'site'   => $site,
            'nav'    => [
                'active' => $navActive,
                'links'  => $links,
                'auth'   => [
                    'is_auth'  => $isAuth,
                    'is_admin' => $isAdmin,
                    'username' => $isAuth ? (string)($usr['username'] ?? '') : '',
                ],
            ],
        ]);
    }
}
