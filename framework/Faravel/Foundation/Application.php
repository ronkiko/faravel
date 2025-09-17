<?php // v0.4.1
/* framework/Faravel/Foundation/Application.php
Purpose: Ядро Faravel-приложения: DI-контейнер + жизненный цикл (регистрация провайдеров,
         загрузка маршрутов, boot). Совместимо с конфигами и инициализацией Env/Config.
FIX: Вернул методы жизненного цикла registerConfiguredProviders()/loadRoutes()/boot(), чтобы
     bootstrap оставался тонким и вызывал только эти шаги по порядку.
*/

namespace Faravel\Foundation;

use ArrayAccess;
use Closure;
use RuntimeException;
use Faravel\Support\Config;
use Faravel\Support\Env;

// For debug logging. We use the application's Logger to trace lifecycle steps.
use App\Support\Logger;

class Application implements ArrayAccess
{
    /** Глобальный текущий инстанс приложения (для base_path() и т.п.). */
    protected static ?Application $instanceRef = null;

    /** Корень проекта. */
    protected string $basePath;

    /**
     * @var array<string,array{concrete:mixed,singleton:bool,resolved:bool,instance:mixed}>
     */
    protected array $bindings = [];

    /** @var array<int,object> Зарегистрированные инстансы провайдеров. */
    protected array $providers = [];

    protected bool $booted = false;

    /**
     * @param string $basePath Абсолютный путь до корня проекта.
     * @throws RuntimeException Если путь пустой.
     */
    public function __construct(string $basePath)
    {
        $bp = rtrim($basePath, '/');
        if ($bp === '') {
            throw new RuntimeException('Application base path must not be empty.');
        }
        $this->basePath = $bp;

        // Базовые биндинги
        $this->instance('app', $this);
        $this->instance('path.base', $this->basePath);
        $this->instance('path.config', $this->basePath . '/config');
        $this->instance('path.routes', $this->basePath . '/routes');
        $this->instance('path.framework', $this->basePath . '/framework');
        $this->instance('path.app', $this->basePath . '/app');
        $this->instance('path.resources', $this->basePath . '/resources');
    }

    /* ===================== Статический доступ ===================== */

    /**
     * Зафиксировать текущий инстанс приложения (для хелперов/фасадов).
     *
     * @param Application|null $app Текущий инстанс или null, чтобы сбросить.
     * @return void
     */
    public static function setInstance(?Application $app): void
    {
        self::$instanceRef = $app;
    }

    /**
     * Получить текущий инстанс приложения.
     *
     * @return Application
     * @throws RuntimeException Если инстанс ещё не установлен.
     */
    public static function getInstance(): Application
    {
        if (!self::$instanceRef) {
            throw new RuntimeException('Application instance is not set.');
        }
        return self::$instanceRef;
    }

    /**
     * Абсолютный путь к корню проекта.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /* ======================== Контейнер ======================== */

    /**
     * Зарегистрировать фабрику (не singleton). Каждый make() создаёт новый объект.
     *
     * @param string $id Идентификатор биндинга.
     * @param mixed  $concrete Closure|class-string|mixed фабрика или значение.
     * @return void
     */
    public function bind(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = [
            'concrete'  => $concrete,
            'singleton' => false,
            'resolved'  => false,
            'instance'  => null,
        ];
    }

    /**
     * Зарегистрировать singleton-фабрику (или класс). Создаётся один раз.
     *
     * @param string $id Идентификатор биндинга.
     * @param mixed  $concrete Closure|class-string|mixed фабрика или значение.
     * @return void
     */
    public function singleton(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = [
            'concrete'  => $concrete,
            'singleton' => true,
            'resolved'  => false,
            'instance'  => null,
        ];
    }

    /**
     * Поместить готовый инстанс как singleton.
     *
     * @param string $id Идентификатор биндинга.
     * @param mixed  $object Объект/значение.
     * @return void
     */
    public function instance(string $id, mixed $object): void
    {
        $this->bindings[$id] = [
            'concrete'  => $object,
            'singleton' => true,
            'resolved'  => true,
            'instance'  => $object,
        ];
    }

    /**
     * Разрешить биндинг по id.
     *
     * @param string $id
     * @return mixed
     * @throws RuntimeException Если биндинг не найден.
     */
    public function make(string $id): mixed
    {
        if (!array_key_exists($id, $this->bindings)) {
            throw new RuntimeException("Container binding [{$id}] not found.");
        }

        $def = &$this->bindings[$id];

        if ($def['singleton'] && $def['resolved']) {
            return $def['instance'];
        }

        $created = $this->build($def['concrete']);

        if ($def['singleton']) {
            $def['instance'] = $created;
            $def['resolved'] = true;
        }

        return $created;
    }

    /**
     * Синоним make() для иного стиля кода.
     *
     * @param string $id
     * @return mixed
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Построение объекта из фабрики/класса/значения.
     *
     * @param mixed $concrete Closure|class-string|mixed.
     * @return mixed
     */
    protected function build(mixed $concrete): mixed
    {
        // 1) Closure (factory). Передаём $app, если параметр ожидается.
        if ($concrete instanceof Closure) {
            $ref = new \ReflectionFunction($concrete);
            return $ref->getNumberOfParameters() > 0
                ? $concrete($this)
                : $concrete();
        }

        // 2) Класс — инстанцируем без аргументов.
        if (is_string($concrete) && class_exists($concrete)) {
            return new $concrete();
        }

        // 3) Готовое значение.
        return $concrete;
    }

    /* =============== ArrayAccess для $app['config'] =============== */

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string)$offset, $this->bindings);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->make((string)$offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // По умолчанию считаем переданное значение готовым singleton-инстансом.
        $this->instance((string)$offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->bindings[(string)$offset]);
    }

    /* ===================== Жизненный цикл ===================== */

    /**
     * Полная регистрация провайдеров, как это делал bootstrap.
     *
     * Шаги:
     *  1) Загрузка .env (если класс Env доступен).
     *  2) Загрузка config/*.php (если класс Config доступен), помещение snapshot в контейнер.
     *  3) Создание и регистрация провайдеров из config('app.providers').
     *
     * Предусловия:
     *  - Инстанс приложения установлен (setInstance), если конфиги вызывают base_path().
     *
     * Побочные эффекты:
     *  - Чтение файлов .env и config/*.php.
     *  - Создание объектов провайдеров и вызов их register().
     *
     * @return void
     * @throws RuntimeException При некорректном basePath.
     * @example $app->registerConfiguredProviders();
     */
    public function registerConfiguredProviders(): void
    {
        // Debug: begin provider registration
        Logger::log('APP.REGISTERPROVIDERS.START', 'Start registering configured providers');

        // 1) .env
        if (class_exists(Env::class)) {
            Env::load($this->basePath);
        }

        // 2) config/*.php
        if (class_exists(Config::class)) {
            Config::load($this->basePath . '/config');
            // Снимок конфигов в контейнер (по желанию можно использовать репозиторий напрямую).
            if (!isset($this->bindings['config'])) {
                $this->instance('config', Config::all());
            }
        }

        // 3) Провайдеры
        $providers = class_exists(Config::class)
            ? (array) Config::get('app.providers', [])
            : [];

        foreach ($providers as $providerClass) {
            if (!class_exists($providerClass)) {
                continue;
            }
            /** @var object $provider */
            $provider = new $providerClass($this);
            $this->providers[] = $provider;
            // Debug: indicate provider is being registered
            Logger::log('APP.PROVIDER.REGISTER', (string) $providerClass);

            if (method_exists($provider, 'register')) {
                $provider->register();
            }
        }

        // Debug: end provider registration
        Logger::log('APP.REGISTERPROVIDERS.END', 'Finished registering providers');
    }

    /**
     * Подключение маршрутов приложения (routes/web.php).
     *
     * Предусловия: в контейнере уже доступны сервисы, которые могут потребоваться роутам.
     * Побочные эффекты: исполняет код routes/web.php.
     *
     * @return void
     * @example $app->loadRoutes();
     */
    public function loadRoutes(): void
    {
        // Debug: begin route loading
        Logger::log('APP.LOADROUTES.START', 'Loading routes');

        $path = $this->basePath . '/routes/web.php';
        if (is_file($path)) {
            /** @noinspection PhpIncludeInspection */
            require_once $path;
        }

        // Debug: end route loading
        Logger::log('APP.LOADROUTES.END', 'Routes loaded');
    }

    /**
     * Boot-фаза провайдеров: вызывает их метод boot(), если он определён.
     *
     * Идемпотентность: выполняется один раз за жизненный цикл приложения.
     *
     * @return void
     * @example $app->boot();
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        // Debug: begin booting
        Logger::log('APP.BOOT.START', 'Booting providers');

        foreach ($this->providers as $p) {
            if (method_exists($p, 'boot')) {
                $p->boot();
            }
        }
        $this->booted = true;

        // Debug: finished booting
        Logger::log('APP.BOOT.END', 'Boot complete');
    }
}
