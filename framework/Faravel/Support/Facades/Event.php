<?php // v0.4.1
/* framework/Faravel/Support/Facades/Event.php
Назначение: фасад диспетчера событий; предоставляет доступ к 'events' из контейнера
приложения (Application).
FIX: переведён с legacy Faravel\Container\Container на базовый Facade.
*/

namespace Faravel\Support\Facades;

/**
 * @method static void listen(string $event, callable $listener)
 * @method static void dispatch(object|string $event, mixed ...$payload)
 * @method static bool hasListeners(string $event)
 */
class Event extends Facade
{
    /**
     * Имя компонента контейнера для разрешения.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'events';
    }
}
