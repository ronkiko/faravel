<?php // v0.4.4
/* app/Http/ViewModels/Layout/LayoutVM.php
Purpose: Layout ViewModel (site/nav/title/locale) — единый контракт для «немых»
         Blade-вью Faravel. Жёстко фиксирует ключи, чтобы исключить рассинхрон.
FIX: Полностью удалён DEPRECATED-алиас brand.*. Контракт окончательно переведён
     на site.* (site.logo.url, site.home.url, site.title). nav.active обязателен.
*/
namespace App\Http\ViewModels\Layout;

/**
 * LayoutVM — data for layout-level partials (banner/nav). No logic inside.
 */
final class LayoutVM
{
    /** @var string UI locale */
    public string $locale = 'ru';

    /** @var string Page <title> (not a part of site banner) */
    public string $title = 'Faravel';

    /**
     * Site banner block (above navbar).
     *
     * @var array{
     *   title:string,
     *   logo:array{url:string},
     *   home:array{url:string}
     * }
     */
    public array $site = [
        'title' => 'FARAVEL',
        'logo'  => ['url' => '/style/logo.png'],
        'home'  => ['url' => '/'],
    ];

    /**
     * Navigation state (mandatory `active`).
     *
     * @var array{
     *   active:string,
     *   links:array<string,string>,
     *   auth:array{is_auth:bool,is_admin:bool,username:string}
     * }
     */
    public array $nav = [
        'active' => 'home',
        'links'  => [],
        'auth'   => ['is_auth' => false, 'is_admin' => false, 'username' => ''],
    ];

    /**
     * Create ViewModel from array produced by the service layer.
     *
     * Preconditions:
     * - 'title' present as string;
     * - 'site' present with keys: site.title, site.logo.url, site.home.url (all strings);
     * - 'nav' present with 'active' (non-empty string), 'links' map, 'auth' map.
     *
     * Side effects: none.
     *
     * @param array<string,mixed> $data Full layout payload.
     * @return static
     * @throws \InvalidArgumentException If required keys are missing or types mismatch.
     * @example
     *   $vm = LayoutVM::fromArray([
     *     'title' => 'Login',
     *     'site'  => [
     *       'title' => 'FARAVEL',
     *       'logo'  => ['url' => '/style/logo.png'],
     *       'home'  => ['url' => '/'],
     *     ],
     *     'nav' => [
     *       'active' => 'login',
     *       'links'  => [
     *         'home'=>'/', 'forum'=>'/forum/', 'login'=>'/login', 'register'=>'/register'
     *       ],
     *       'auth'   => ['is_auth'=>false,'is_admin'=>false,'username'=>''],
     *     ],
     *   ]);
     */
    public static function fromArray(array $data): static
    {
        $self = new self();

        // locale (optional)
        if (isset($data['locale'])) {
            if (!is_string($data['locale'])) {
                throw new \InvalidArgumentException('layout.locale must be string');
            }
            $self->locale = $data['locale'];
        }

        // title (required)
        if (!isset($data['title']) || !is_string($data['title'])) {
            throw new \InvalidArgumentException('layout.title must be string');
        }
        $self->title = $data['title'];

        // site.* (required)
        if (!isset($data['site']) || !is_array($data['site'])) {
            throw new \InvalidArgumentException('layout.site must be array');
        }
        $site = $data['site'];

        if (!isset($site['title']) || !is_string($site['title'])) {
            throw new \InvalidArgumentException('layout.site.title must be string');
        }
        if (!isset($site['logo']['url']) || !is_string($site['logo']['url'])) {
            throw new \InvalidArgumentException('layout.site.logo.url must be string');
        }
        if (!isset($site['home']['url']) || !is_string($site['home']['url'])) {
            throw new \InvalidArgumentException('layout.site.home.url must be string');
        }
        $self->site = [
            'title' => $site['title'],
            'logo'  => ['url' => $site['logo']['url']],
            'home'  => ['url' => $site['home']['url']],
        ];

        // nav.* (required)
        if (!isset($data['nav']) || !is_array($data['nav'])) {
            throw new \InvalidArgumentException('layout.nav must be array');
        }
        $nav = $data['nav'];

        if (!isset($nav['active']) || !is_string($nav['active']) || $nav['active'] === '') {
            throw new \InvalidArgumentException('layout.nav.active must be non-empty string');
        }
        if (!isset($nav['links']) || !is_array($nav['links'])) {
            throw new \InvalidArgumentException('layout.nav.links must be array<string,string>');
        }
        if (!isset($nav['auth']) || !is_array($nav['auth'])) {
            throw new \InvalidArgumentException('layout.nav.auth must be array');
        }

        // normalize links to <string,string>
        $links = [];
        foreach ($nav['links'] as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                throw new \InvalidArgumentException(
                    'layout.nav.links must be array<string,string>'
                );
            }
            $links[$k] = $v;
        }

        $auth = $nav['auth'];
        $isAuth   = (bool)($auth['is_auth']  ?? false);
        $isAdmin  = (bool)($auth['is_admin'] ?? false);
        $username = (string)($auth['username'] ?? '');

        $self->nav = [
            'active' => $nav['active'],
            'links'  => $links,
            'auth'   => [
                'is_auth'  => $isAuth,
                'is_admin' => $isAdmin,
                'username' => $username,
            ],
        ];

        return $self;
    }

    /**
     * Export to array for strict Blade views.
     *
     * @return array{
     *   locale:string,
     *   title:string,
     *   site:array{title:string,logo:array{url:string},home:array{url:string}},
     *   nav:array<string,mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'title'  => $this->title,
            'site'   => $this->site,
            'nav'    => $this->nav,
        ];
    }
}
