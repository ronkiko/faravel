<?php // v0.4.5
/* framework/Faravel/Support/Facades/Facade.php
Purpose: базовый фасад (Laravel-like) — перенаправляет статические вызовы
к реальным экземплярам из контейнера приложения.
FIX: добавлены setApplication()/getApplication() для явной инициализации контейнера
из bootstrap; getFacadeRoot() теперь использует сохранённый контейнер, а также
сбрасывается кэш резолвов при смене приложения.
*/
namespace Faravel\Support\Facades;

/**
 * Minimal base Facade (Laravel-like).
 * Static calls are proxied to an underlying instance from the container.
 */
abstract class Facade
{
    /** @var array<class-string,object> */
    protected static array $resolved = [];

    /** @var object|null Глобальная ссылка на контейнер приложения. */
    protected static ?object $app = null;

    /**
     * Return the container key (service id) this facade resolves.
     *
     * @return string
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Установить контейнер приложения (вызывается на bootstrap).
     *
     * @param object $app Контейнер, поддерживающий метод make(string): object.
     * @return void
     */
    public static function setApplication(object $app): void
    {
        static::$app = $app;
        // Меняется приложение — сбросить кэш уже резолвленных объектов.
        static::$resolved = [];
    }

    /**
     * Получить контейнер приложения, если он установлен.
     *
     * @return object|null
     */
    public static function getApplication(): ?object
    {
        if (static::$app) {
            return static::$app;
        }
        // Fallback: глобальный helper \app() может быть доступен позднее.
        if (function_exists('\\app')) {
            try {
                /** @var object $container */
                $container = \app();
                return static::$app = $container;
            } catch (\Throwable) {
                // ignore and return null
            }
        }
        return null;
    }

    /**
     * Get the underlying object behind the facade.
     *
     * @return object
     *
     * @throws \RuntimeException If service cannot be resolved.
     */
    public static function getFacadeRoot(): object
    {
        $key = static::getFacadeAccessor();

        if (isset(static::$resolved[$key])) {
            return static::$resolved[$key];
        }

        $app = static::getApplication();
        if (!$app || !method_exists($app, 'make')) {
            throw new \RuntimeException('Facade container is not available to resolve: ' . $key);
        }

        /** @var object $obj */
        $obj = $app->make($key);
        return static::$resolved[$key] = $obj;
    }

    /**
     * Magic proxy for static calls to the underlying instance.
     *
     * @param string $method
     * @param array<int,mixed> $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getFacadeRoot();

        // dynamic call by design
        return $instance->$method(...$args);
    }
}
