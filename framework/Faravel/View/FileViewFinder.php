<?php // v0.4.1
/* framework/Faravel/View/FileViewFinder.php
Назначение: реализация поисковика представлений — находит файлы по именам
вида ('forum.index') в заданных директориях и с поддерживаемыми расширениями.
FIX: реализован Contracts\ViewFinderInterface; добавлен метод getPaths().
*/
namespace Faravel\View;

use RuntimeException;
use Faravel\View\Contracts\ViewFinderInterface;

/**
 * Поисковик файлов шаблонов.
 * Поддерживает расширения: '.blade.php' и '.php' (по умолчанию).
 */
class FileViewFinder implements ViewFinderInterface
{
    /** @var array<int,string> */
    protected array $paths;

    /** @var array<int,string> */
    protected array $extensions;

    /**
     * @param array<int,string>       $paths      Корневые директории поиска.
     * @param array<int,string>|null  $extensions Список расширений без точки.
     */
    public function __construct(array $paths, ?array $extensions = null)
    {
        $this->paths = array_values(array_map(
            static fn($p) => rtrim((string)$p, DIRECTORY_SEPARATOR),
            $paths
        ));
        // Приоритет: .blade.php, затем .php
        $this->extensions = $extensions
            ? array_values(array_map(static fn($e) => ltrim((string)$e, '.'), $extensions))
            : ['blade.php', 'php'];
    }

    /**
     * Добавить директорию в список поиска.
     *
     * @param string $path Абсолютный путь.
     * @return void
     */
    public function addLocation(string $path): void
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($path !== '' && !in_array($path, $this->paths, true)) {
            $this->paths[] = $path;
        }
    }

    /**
     * Добавить расширение (без точки) в список поддерживаемых.
     *
     * @param string $ext
     * @return void
     */
    public function addExtension(string $ext): void
    {
        $ext = ltrim($ext, '.');
        if ($ext !== '' && !in_array($ext, $this->extensions, true)) {
            $this->extensions[] = $ext;
        }
    }

    /**
     * @inheritDoc
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * @inheritDoc
     */
    public function find(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('View name cannot be empty.');
        }

        // 'forum.index' → 'forum/index'
        $relative = str_replace(['::', '\\', '.'], ['.', '/', '/'], $name);
        $relative = ltrim($relative, '/');

        foreach ($this->paths as $base) {
            foreach ($this->extensions as $ext) {
                $candidate = $base . DIRECTORY_SEPARATOR . $relative . '.' . $ext;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        $pathsStr = $this->paths ? implode(', ', $this->paths) : '(no paths)';
        $extStr   = $this->extensions ? implode(', ', $this->extensions) : '(no extensions)';

        throw new RuntimeException(
            "View not found: {$name}. Searched in: {$pathsStr}. With extensions: {$extStr}."
        );
    }
}
