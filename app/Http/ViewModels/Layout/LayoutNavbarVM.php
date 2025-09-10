<?php // v0.1.0
/* app/Http/ViewModels/Layout/LayoutNavbarVM.php — v0.1.0
Назначение: VM навбара — готовые данные для шаблона (строгий Blade).
FIX: начальная версия; все ссылки и флаги подготовлены заранее.
*/
namespace App\Http\ViewModels\Layout;

final class LayoutNavbarVM
{
    /** @param array{is_auth:bool,username?:string,avatar_url?:string,is_admin?:bool} $auth */
    public static function fromAuth(array $auth): array
    {
        $is = (bool)($auth['is_auth'] ?? false);
        $name = (string)($auth['username'] ?? '');
        $isAdmin = (bool)($auth['is_admin'] ?? false);

        return [
            'auth'  => [
                'is_auth'   => $is,
                'username'  => $name,
                'avatar'    => (string)($auth['avatar_url'] ?? ''),
                'is_admin'  => $isAdmin,
            ],
            'links' => [
                'home'    => '/',
                'forum'   => '/forum/',
                'login'   => '/login',
                'logout'  => '/logout',
                'profile' => '/me',
                'admin'   => '/admin',
            ],
        ];
    }
}
