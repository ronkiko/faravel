<?php // v0.4.5
/* framework/Faravel/View/ViewFactory.php
Purpose: фабрика представлений Faravel. Ищет шаблон, выбирает движок, мёрджит
shared+локальные данные, создаёт View и запускает view-композеры.
FIX: добавлен вызов композеров через контейнер app(), если доступен; мелкие правки стиля.
*/
namespace Faravel\View;

use InvalidArgumentException;
use RuntimeException;
// В проекте реально присутствует FileViewFinder — используем его.
use Faravel\View\FileViewFinder;

class ViewFactory
{
    /** @var FileViewFinder */
    protected FileViewFinder $finder;

    /** @var array<string,object> */
    protected array $engines = [];

    /** @var array<string,mixed> */
    protected array $shared = [];

    /**
     * Предкомпилированные правила композеров.
     * @var array<int,array{regex:string,handler:callable|string}>
     */
    protected array $composers = [];

    /**
     * @param FileViewFinder $finder
     */
    public function __construct(FileViewFinder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Зарегистрировать движок для расширения.
     *
     * @param string $extension e.g. 'php' or 'blade.php'
     * @param object $engine    must implement get(string $path, array $data): string
     * @return void
     */
    public function addEngine(string $extension, object $engine): void
    {
        $this->engines[ltrim($extension, '.')] = $engine;
    }

    /**
     * Create a View by its dotted name (e.g. 'forum.index').
     *
     * @param string               $view  Dotted view name.
     * @param array<string,mixed>  $data  Local data.
     * @return View
     */
    public function make(string $view, array $data = []): View
    {
        $path = $this->finder->find($view);
        $ext  = $this->extForPath($path);
        $engine = $this->engines[$ext] ?? null;
        if (!$engine) {
            throw new RuntimeException("No engine is registered for extension: .{$ext}");
        }

        $payload  = $this->mergeData($data);
        $instance = new View($path, $payload, $engine);

        $this->runComposers($view, $instance);

        return $instance;
    }

    /**
     * Create a View by full file path (rarely used).
     *
     * @param string               $path Full path to the template.
     * @param array<string,mixed>  $data Local data.
     * @return View
     */
    public function file(string $path, array $data = []): View
    {
        $ext  = $this->extForPath($path);
        $engine = $this->engines[$ext] ?? null;
        if (!$engine) {
            throw new RuntimeException("No engine is registered for extension: .{$ext}");
        }

        $name = $this->nameFromPath($path);
        $payload  = $this->mergeData($data);
        $instance = new View($path, $payload, $engine);

        $this->runComposers($name, $instance);

        return $instance;
    }

    /**
     * Register one or many patterns to a composer handler.
     *
     * @param string|array<int,string> $patterns
     * @param callable|string          $composer Callable or class-string with compose().
     * @return void
     */
    public function composer(string|array $patterns, callable|string $composer): void
    {
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        if (!$patterns) {
            throw new InvalidArgumentException('composer(): patterns cannot be empty.');
        }
        foreach ($patterns as $p) {
            $regex = $this->patternToRegex($p);
            $this->composers[] = ['regex' => $regex, 'handler' => $composer];
        }
    }

    /**
     * Share a variable with every view created within current request.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Get shared payload (mostly for tests / debugging).
     *
     * @return array<string,mixed>
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Convert a Laravel-like pattern to PCRE.
     * 'forum.*' → '/^forum\..*$/i'
     *
     * @param string $pattern
     * @return string
     */
    protected function patternToRegex(string $pattern): string
    {
        $quoted = preg_quote($pattern, '/');
        $regex  = str_replace('\*', '.*', $quoted);
        return '/^' . $regex . '$/i';
    }

    /**
     * Run all composers matching given logical view name.
     *
     * @param string $viewName
     * @param View   $view
     * @return void
     */
    protected function runComposers(string $viewName, View $view): void
    {
        if (!$this->composers) {
            return;
        }
        foreach ($this->composers as $c) {
            if (@preg_match($c['regex'], $viewName) !== 1) {
                continue;
            }
            $handler = $c['handler'];

            if (is_string($handler)) {
                $instance = null;
                if (function_exists('app')) {
                    try { $instance = app($handler, null); } catch (\Throwable $e) { $instance = null; }
                }
                if (!$instance) {
                    $instance = new $handler();
                }
                if (!method_exists($instance, 'compose')) {
                    throw new RuntimeException(
                        "View composer {$handler} must define compose() method."
                    );
                }
                $instance->compose($view);
                continue;
            }

            if (is_callable($handler)) {
                $handler($view);
                continue;
            }

            throw new RuntimeException('Invalid composer handler type.');
        }
    }

    /**
     * Merge shared and local payload (local has priority).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    protected function mergeData(array $data): array
    {
        return $this->shared ? array_merge($this->shared, $data) : $data;
    }

    /**
     * Guess extension key for registered engines (e.g., 'php', 'blade.php').
     *
     * @param string $path
     * @return string
     */
    protected function extForPath(string $path): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext === 'php' && str_ends_with($path, '.blade.php')) {
            return 'blade.php';
        }
        return $ext ?: 'php';
    }

    /**
     * Try to reconstruct logical dotted name from full path using finder base paths.
     *
     * @param string $path
     * @return string
     */
    protected function nameFromPath(string $path): string
    {
        // На случай, если в твоём классе есть getPaths() — используем его.
        $paths = method_exists($this->finder, 'getPaths') ? (array)$this->finder->getPaths() : [];
        $norm  = str_replace('\\', '/', $path);

        foreach ($paths as $base) {
            $base = rtrim(str_replace('\\', '/', (string)$base), '/');
            if ($base !== '' && str_starts_with($norm, $base . '/')) {
                $rel = substr($norm, strlen($base) + 1);
                $rel = preg_replace('/\.[^.]+$/', '', (string)$rel);
                return str_replace('/', '.', (string)$rel);
            }
        }

        $rel = preg_replace('/\.[^.]+$/', '', $norm);
        return ltrim(str_replace('/', '.', (string)$rel), '.');
    }
}
