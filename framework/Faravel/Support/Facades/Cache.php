<?php // v0.4.1
/* framework/Faravel/Support/Facades/Cache.php
Назначение: фасад кеша; проксирует статические вызовы к сервису 'cache' в контейнере
приложения (Application).
FIX: переведён с legacy Faravel\Container\Container на базовый Facade.
*/

namespace Faravel\Support\Facades;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void  put(string $key, mixed $value, int $seconds = 0)
 * @method static bool  has(string $key)
 * @method static void  forget(string $key)
 * @method static void  flush()
 */
class Cache extends Facade
{
    /**
     * Имя компонента контейнера для разрешения.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
