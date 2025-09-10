<?php // v0.4.1
/* framework/Faravel/Support/Facades/Session.php
Purpose: Laravel-стайл фасад для сессии. Проксирует статические вызовы к Faravel\Http\Session,
что позволяет писать Session::get(), Session::put() и т.п. в коде приложения.
FIX: Новый файл. Привязан к контейнерному биндингу \Faravel\Http\Session::class.
*/
namespace Faravel\Support\Facades;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void put(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static array all()
 * @method static string id()
 * @method static void regenerate()
 */
final class Session extends Facade
{
    /**
     * Возвращает ключ биндера контейнера для разрешения реального объекта Session.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Faravel\Http\Session::class;
    }
}
