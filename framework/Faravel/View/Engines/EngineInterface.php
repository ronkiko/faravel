<?php // v0.4.1
/* framework/Faravel/View/Engines/EngineInterface.php
Purpose: общий контракт для движков рендеринга (Blade, Php и т.п.), чтобы
ViewFactory могла работать с ними полиморфно (как в Laravel).
FIX: новый файл — введён интерфейс, устраняет ошибку Intelephense о неизвестном типе.
*/
namespace Faravel\View\Engines;

/**
 * Rendering engine contract (Laravel-like).
 * Any engine must implement a simple "get" method.
 */
interface EngineInterface
{
    /**
     * Render a template file with the given data payload.
     *
     * @param string               $path  Absolute template path.
     * @param array<string,mixed>  $data  Variables to extract into scope.
     *
     * Preconditions:
     * - $path must be an existing, readable file.
     *
     * Side effects:
     * - May perform output buffering; no global state mutation required.
     *
     * @return string Rendered output.
     *
     * @throws \RuntimeException On I/O or template errors.
     *
     * @example
     *  $html = $engine->get('/abs/view.php', ['title' => 'Home']);
     */
    public function get(string $path, array $data): string;
}
