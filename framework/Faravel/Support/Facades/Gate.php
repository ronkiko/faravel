<?php // v0.4.2
/* framework/Faravel/Support/Facades/Gate.php
Purpose: Facade to resolve the 'gate' service (authorization manager) from the container.
FIX: PHP 8.1+ — added explicit return type to getFacadeAccessor(): string (signature parity).
*/

namespace Faravel\Support\Facades;

/**
 * Thin static facade for the Gate service. Real logic is in the service itself.
 */
final class Gate extends Facade
{
    /**
     * Return container key for the underlying gate service.
     *
     * Предусловия: в контейнере зарегистрирован singleton по ключу 'gate'.
     * Побочные эффекты: нет.
     *
     * @return string Container binding id.
     * @example
     *  Gate::define('topic.reply', fn($user, $topic) => (bool)$user && $topic->isOpen());
     */
    protected static function getFacadeAccessor(): string
    {
        return 'gate';
    }
}
