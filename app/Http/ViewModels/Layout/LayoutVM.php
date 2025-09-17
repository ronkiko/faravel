<?php // v0.4.123
/* app/Http/ViewModels/Layout/LayoutVM.php
Purpose: ViewModel уровня лэйаута (site/nav/title/locale) для строгих Blade-шаблонов.
         Жёстко нормализует ключи, включая nav.show.{admin,mod}.
FIX: Добавлен верхнеуровневый layout.csrf для форм страницы (logout и прочих),
     чтобы Blade оставался «тупым» и не вызывал хелперы. Контракт расширен и
     согласован с LayoutService. DEPRECATED brand.* не используется.
*/
namespace App\Http\ViewModels\Layout;

/**
 * LayoutVM — данные для макета и навигации. Никакой логики внутри шаблонов.
 */
final class LayoutVM
{
    /** @var string UI locale */
    public string $locale = 'ru';

    /** @var string Page <title> */
    public string $title = 'Faravel';

    /** @var string CSRF token available to any page forms */
    public string $csrf = '';

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
     *   show:array{admin:bool,mod:bool},
     *   auth:array{is_auth:bool,is_admin:bool,username:string}
     * }
     */
    public array $nav = [
        'active' => 'home',
        'links'  => [],
        'show'   => ['admin' => false, 'mod' => false],
        'auth'   => ['is_auth' => false, 'is_admin' => false, 'username' => ''],
    ];

    /**
     * Создать VM из массива, собранного сервисом.
     *
     * Preconditions:
     * - 'title' is string;
     * - 'csrf' is non-empty string (для форм);
     * - 'site' содержит: site.title, site.logo.url, site.home.url (strings);
     * - 'nav' содержит: 'active', 'links', 'show', 'auth'.
     *
     * @param array<string,mixed> $data
     * @return static
     * @throws \InvalidArgumentException On missing keys or type mismatches.
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

        // csrf (required — пустой токен нам не подходит)
        if (!isset($data['csrf']) || !is_string($data['csrf']) || $data['csrf'] === '') {
            throw new \InvalidArgumentException('layout.csrf must be non-empty string');
        }
        $self->csrf = $data['csrf'];

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
        if (!isset($nav['show']) || !is_array($nav['show'])) {
            throw new \InvalidArgumentException('layout.nav.show must be array');
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

        // normalize show
        $show = [
            'admin' => (bool)($nav['show']['admin'] ?? false),
            'mod'   => (bool)($nav['show']['mod'] ?? false),
        ];

        // normalize auth
        $authArr = $nav['auth'];
        $isAuth   = (bool)($authArr['is_auth']  ?? false);
        $isAdmin  = (bool)($authArr['is_admin'] ?? false);
        $username = (string)($authArr['username'] ?? '');

        $self->nav = [
            'active' => $nav['active'],
            'links'  => $links,
            'show'   => $show,
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
     *   csrf:string,
     *   site:array{title:string,logo:array{url:string},home:array{url:string}},
     *   nav:array{
     *     active:string,
     *     links:array<string,string>,
     *     show:array{admin:bool,mod:bool},
     *     auth:array{is_auth:bool,is_admin:bool,username:string}
     *   }
     * }
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'title'  => $this->title,
            'csrf'   => $this->csrf,
            'site'   => $this->site,
            'nav'    => $this->nav,
        ];
    }
}
