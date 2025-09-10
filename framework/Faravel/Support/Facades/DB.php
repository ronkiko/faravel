<?php // v0.4.1
/* framework/Faravel/Support/Facades/DB.php
Назначение: фасад менеджера базы данных Faravel; проксирует статические вызовы
к сервису 'db' в контейнере приложения (Application).
FIX: переведён с legacy Faravel\Container\Container на базовый Facade, который
использует Faravel\Foundation\Application (через Facade::setApplication()).
*/

namespace Faravel\Support\Facades;

/**
 * @method static \Faravel\Database\QueryBuilder table(string $table)
 * @method static array select(string $sql, array $bindings = [])
 * @method static mixed scalar(string $sql, array $bindings = [])
 * @method static int   insert(string $sql, array $bindings = [])
 * @method static int   update(string $sql, array $bindings = [])
 * @method static int   delete(string $sql, array $bindings = [])
 */
class DB extends Facade
{
    /**
     * Имя компонента контейнера для разрешения.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'db';
    }
}
