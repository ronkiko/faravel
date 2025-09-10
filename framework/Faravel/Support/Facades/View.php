<?php // v0.4.3
/* framework/Faravel/Support/Facades/View.php
Purpose: фасад сервиса 'view' (ViewFactory) — статический доступ к API фабрики,
включая регистрацию композеров и работу с shared-данными.
FIX: явное наследование от полностью квалифицированного Facade для корректного
резолва Intelephense (без новых файлов). Обновлены докблоки.
*/
namespace Faravel\Support\Facades;

use Faravel\View\ViewFactory;

/**
 * @method static \Faravel\View\View make(string $view, array $data=[])
 * @method static \Faravel\View\View file(string $path, array $data=[])
 * @method static void share(string $key, mixed $value)
 * @method static array<string,mixed> getShared()
 */
class View extends \Faravel\Support\Facades\Facade
{
    /**
     * Service key in the container.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }

    /**
     * Proxy to ViewFactory::composer().
     *
     * @param string|array<int,string> $patterns
     * @param callable|string          $composer
     * @return void
     */
    public static function composer(string|array $patterns, callable|string $composer): void
    {
        /** @var ViewFactory $factory */
        $factory = static::getFacadeRoot();
        $factory->composer($patterns, $composer);
    }
}
