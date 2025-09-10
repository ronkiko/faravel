<?php // v0.4.1
/* framework/Faravel/View/Contracts/ViewFinderInterface.php
Назначение: контракт поисковика представлений (упрощённый аналог
Illuminate\View\ViewFinderInterface). Нужен, чтобы ViewFactory зависела
от абстракции, а не от FileViewFinder (по-ларовеловски).
FIX: новый файл — введён контракт для корректной типизации и работы
Intelephense без ошибок P1009.
*/
namespace Faravel\View\Contracts;

/**
 * Интерфейс поисковика файлов шаблонов.
 * Реализации обязаны уметь находить абсолютный путь по имени вида
 * и сообщать список корневых директорий поиска.
 */
interface ViewFinderInterface
{
    /**
     * Найти абсолютный путь к файлу представления по его логическому имени.
     * Имя задаётся в «точечной» нотации: 'forum.index', 'layouts.theme'.
     *
     * @param string $name
     * Preconditions: $name не пустая строка.
     * @return string Абсолютный путь к существующему файлу.
     * @throws \RuntimeException Если файл не найден.
     */
    public function find(string $name): string;

    /**
     * Список корневых директорий, в которых производится поиск видов.
     *
     * @return array<int,string> Абсолютные пути.
     */
    public function getPaths(): array;
}
