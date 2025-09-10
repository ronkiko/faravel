<?php // v0.4.1
/* framework/Faravel/Support/Facades/Auth.php
Назначение: фасад auth-сервиса; проксирует статические вызовы к 'auth' в контейнере
приложения (Application).
FIX: переведён с ручного обращения к legacy-контейнеру на базовый Facade.
*/

namespace Faravel\Support\Facades;

/**
 * @method static bool   check()
 * @method static bool   guest()
 * @method static string|null id()
 * @method static array<string,mixed>|null user()
 * @method static void   login(string $userId)
 * @method static void   logout()
 */
class Auth extends Facade
{
    /**
     * Имя компонента контейнера для разрешения.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'auth';
    }
}
