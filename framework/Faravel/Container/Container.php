<?php // v0.4.1
/* framework/Faravel/Container/Container.php
Назначение: совместимостьный слой для старого контейнера. Проксирует все вызовы
в Faravel\Foundation\Application и помечен как DEPRECATED.
FIX: вместо самостоятельного контейнера выполнен тонкий shim поверх Application:
getInstance()/setInstance()/make()/bind()/singleton()/has()/ArrayAccess — всё
делегировано в новый контейнер. Это безопасно и устраняет раздвоение DI.
*/

namespace Faravel\Container;

use ArrayAccess;
use Faravel\Foundation\Application;
use RuntimeException;

/**
 * @deprecated Не использовать напрямую. Оставлен как совместимостьный слой.
 * Вместо него следует работать с Faravel\Foundation\Application и хелпером container().
 */
final class Container implements ArrayAccess
{
    /**
     * Конструктор совместимости. Не создаёт «свой» контейнер; при переданном
     * $basePath гарантирует, что Application инициализирован.
     *
     * @param string|null $basePath Базовый путь, если хотим первично поднять Application.
     */
    public function __construct(?string $basePath = null)
    {
        try {
            Application::getInstance();
        } catch (RuntimeException) {
            // Если Application ещё не создан — создадим
            new Application($basePath);
        }
    }

    /**
     * Возвратить текущий экземпляр контейнера (на деле — Application).
     *
     * @return self
     */
    public static function getInstance(): self
    {
        // Вернём совместимостьный обёрточный объект вокруг Application
        return new self();
    }

    /**
     * Синоним getInstance() для старого API.
     *
     * @return self
     */
    public static function container(): self
    {
        return self::getInstance();
    }

    /**
     * Установить экземпляр контейнера (делегируется в Application).
     *
     * @param Container|Application $container
     * @return void
     */
    public static function setInstance($container): void
    {
        if ($container instanceof Application) {
            Application::setInstance($container);
            return;
        }
        // Если передали обёртку — просто удостоверимся, что Application жив
        Application::getInstance();
    }

    /**
     * Разрешить сервис по ключу/FQCN через Application.
     *
     * @param string $abstract
     * @return mixed
     */
    public function make(string $abstract): mixed
    {
        return Application::getInstance()->make($abstract);
    }

    /**
     * Зарегистрировать обычный биндинг (delegation).
     *
     * @param string   $abstract
     * @param callable $factory
     * @return void
     */
    public function bind(string $abstract, callable $factory): void
    {
        Application::getInstance()->bind($abstract, $factory);
    }

    /**
     * Зарегистрировать singleton-биндинг (delegation).
     *
     * @param string          $abstract
     * @param callable|object $concrete
     * @return void
     */
    public function singleton(string $abstract, callable|object $concrete): void
    {
        Application::getInstance()->singleton($abstract, $concrete);
    }

    /**
     * Проверить наличие биндинга/инстанса (delegation).
     *
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return Application::getInstance()->has($abstract);
    }

    // === ArrayAccess совместимость ===

    public function offsetExists(mixed $offset): bool
    {
        return \is_string($offset) && Application::getInstance()->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!\is_string($offset)) {
            throw new RuntimeException('Array key must be string.');
        }
        return Application::getInstance()->make($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!\is_string($offset)) {
            throw new RuntimeException('Array key must be string.');
        }
        if ($value === null) {
            $this->offsetUnset($offset);
            return;
        }
        if (\is_callable($value)) {
            Application::getInstance()->bind($offset, $value);
            return;
        }
        Application::getInstance()->singleton($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        if (!\is_string($offset)) {
            return;
        }
        // У Application нет явного unset; добавлять не будем — это shim.
        // В учебном DI можно игнорировать.
    }
}
