<?php // v0.3.43

namespace Faravel\Support\Facades;

use Faravel\Support\Facades\Facade;

/*
* Фасад для Blade-движка.
*
* Контейнер должен предоставлять binding 'blade' (экземпляр BladeEngine),
* поддерживающий методы directive()/addDirective() и т.п.
*
* @method static void directive(string $name, callable $compiler)
* @method static void addDirective(string $name, callable $compiler)
* @method static string compileString(string $value)
* @method static string render(string $view, array $data = [])
*/

class Blade extends Facade
{
    /**
     * Имя компонента контейнера для разрешения.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'blade';
    }
}
