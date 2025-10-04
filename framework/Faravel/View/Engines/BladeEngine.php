<?php // v0.4.14
/* framework/Faravel/View/Engines/BladeEngine.php
Purpose: безопасный Blade-движок Faravel со строгими правилами. Поддерживает
директивы макета и include. Все include и extends создают представления через
ViewFactory::make(), чтобы композиторы вызывались как в Laravel.
FIX: Добавлен алиас addDirective() для совместимости с фасадом/провайдерами.
Обновлена COMPILE_SIG (be-0.4.14). Безопасные правки, логика рендера неизменна.
*/

namespace Faravel\View\Engines;

use RuntimeException;
use Faravel\View\ViewFactory;
use Faravel\View\Contracts\ViewFinderInterface;
use Faravel\View\Engines\EngineInterface;
use Faravel\Support\Facades\Cache;

/**
 * Строгий движок Blade.
 * - Запрещает inline PHP и @php/@endphp.
 * - Макетные директивы и include компилируются в PHP, который вызывает фабрику.
 * - Пользовательские директивы обрабатывает BladeDirectiveCompiler.
 * - Скомпилированный PHP кешируется в файловом кеше.
 */
final class BladeEngine implements EngineInterface
{
    /** Строгий режим: запрет inline PHP. */
    private bool $strict = true;

    /** Разрешать только переменные в двойных фигурных скобках. */
    private bool $echoesOnlyVars = true;

    /** Запрет на сырой вывод. */
    private bool $disallowRawEcho = true;

    /** Схлопывать пустые строки в готовом HTML. */
    private bool $collapseEmptyLines = true;

    /** TTL кеша скомпилированных шаблонов (сек.). */
    private int $cacheTtlSeconds = 300;

    /** Метка версии движка для включения в сигнатуру ключа. */
    private const COMPILE_SIG = 'be-0.4.14';

    /** @var array<string,callable> Реестр безопасных директив. */
    private array $directives = [];

    /** Фабрика представлений для include и extends. */
    private ?ViewFactory $factory = null;

    /** Необязательный finder. */
    private ?ViewFinderInterface $finder;

    /**
     * Конструктор: принимает ViewFactory или ViewFinderInterface.
     *
     * @param object|null $resolver ViewFactory|ViewFinderInterface|null
     *
     * @example new BladeEngine($viewFactory)
     * @example new BladeEngine($viewFinder)
     */
    public function __construct(object $resolver = null)
    {
        if ($resolver instanceof ViewFactory) {
            $this->factory = $resolver;
            $this->finder  = null;
        } elseif ($resolver instanceof ViewFinderInterface) {
            $this->finder  = $resolver;
        } else {
            $this->finder  = null;
        }

        // Конфиг: TTL и схлопывание пустых строк — мягко, с дефолтами.
        $ttl = $this->configGetInt('view.blade_cache_ttl', 300);
        $this->cacheTtlSeconds = max(1, $ttl);
        $collapse = $this->configGetBool('view.collapse_empty_lines', true);
        $this->collapseEmptyLines = $collapse;
    }

    /**
     * Зарегистрировать безопасную директиву Blade.
     *
     * Контракт уровня движка для провайдеров/фасада.
     *
     * @param string   $name     Имя директивы (без @).
     * @param callable $compiler Компилятор: принимает выражение
     *                           (string|null) и возвращает PHP-строку.
     *
     * Preconditions:
     * - Возвращаемая строка не должна нарушать строгий режим (никаких
     *   небезопасных эхо, сырого PHP и вызовов внешних сервисов).
     *
     * Side effects: меняет внутренний реестр директив.
     *
     * @return void
     */
    public function directive(string $name, callable $compiler): void
    {
        $this->directives[strtolower($name)] = $compiler;
    }

    /**
     * Алиас к directive(), чтобы соответствовать распространённому контракту
     * фасада Blade и упрощать вызовы из провайдеров.
     *
     * @param string   $name     Имя директивы.
     * @param callable $compiler Компилятор директивы.
     *
     * @return void
     *
     * @example $blade->addDirective('csrf', fn()=>'<input ...>');
     */
    public function addDirective(string $name, callable $compiler): void
    {
        $this->directive($name, $compiler);
    }

    /**
     * Контракт движка для ViewFactory и View.
     *
     * @param string              $path Абсолютный путь к шаблону.
     * @param array<string,mixed> $data Данные представления.
     *
     * @return string Готовый HTML.
     */
    public function get(string $path, array $data): string
    {
        return $this->render($path, $data);
    }

    /**
     * Рендер файла Blade со строгими проверками и фабричными include/extends.
     *
     * @param string              $path Абсолютный путь к шаблону.
     * @param array<string,mixed> $vars Данные представления.
     *
     * @return string Готовый HTML.
     *
     * @throws RuntimeException если файл недоступен или не читается.
     */
    public function render(string $path, array $vars): string
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                "BladeEngine: template file is not readable: {$path}"
            );
        }
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException(
                "BladeEngine: failed to read template: {$path}"
            );
        }

        return $this->renderFromSource($source, $vars, $path);
    }

    /**
     * Рендер из исходника с компиляцией директив и вызовом фабрики.
     *
     * Конвейер:
     *  0a) stripBladeComments — удалить blade-комментарии;
     *  0b) pruneEmptyPushBlocks — убрать пустые блоки push;
     *  0c) removeWhitespaceOnlyLines — удалить пустые строки;
     *  1) transformLayoutsAndIncludes — макетные директивы и include;
     *  2) compileEcho — безопасная компиляция двойных фигурных скобок;
     *  3) BladeDirectiveCompiler::compile — пользовательские директивы;
     *  4) evaluate — исполнение и возможный рендер родителя;
     *  Кеш: на уровне шага 3 сохраняем и читаем скомпилированный PHP.
     *
     * @param string              $source Текст шаблона.
     * @param array<string,mixed> $vars   Данные представления.
     * @param string              $templatePath Путь шаблона (может быть пустым).
     *
     * @return string Готовый HTML.
     */
    public function renderFromSource(
        string $source,
        array $vars,
        string $templatePath = ''
    ): string {
        $this->assertNoForbiddenInlinePhp($source, $templatePath);
        $this->assertNoBladePhpBlocks($source, $templatePath);

        // Попытка взять скомпилированный PHP из кеша.
        $cachedCompiled = $this->fetchCompiledFromCache($templatePath, $source);
        if (is_string($cachedCompiled) && $cachedCompiled !== '') {
            $this->debugDump('3-compiled', $cachedCompiled, $templatePath);
            return $this->evaluate($cachedCompiled, $vars, $templatePath);
        }

        // 0a) Комментарии Blade
        $stage0a = $this->stripBladeComments($source);

        // 0b) Пустые push-блоки
        $stage0b = $this->pruneEmptyPushBlocks($stage0a);

        // 0c) Пустые строки
        $stage0c = $this->removeWhitespaceOnlyLines($stage0b);

        // 1) Макеты и include
        $stage1 = $this->transformLayoutsAndIncludes($stage0c);

        // 2) Эхо в двойных фигурных скобках
        $stage2 = $this->compileEcho($stage1);

        // 3) Пользовательские директивы
        $compiled = BladeDirectiveCompiler::compile($stage2, [
            'disallowRawEcho' => $this->disallowRawEcho,
            'echoesOnlyVars'  => $this->echoesOnlyVars,
            'directives'      => $this->directives,
        ]);

        // Диагностика
        $this->debugDump('0a-stripped',   $stage0a,  $templatePath);
        $this->debugDump('0b-pruned',     $stage0b,  $templatePath);
        $this->debugDump('0c-noblanks',   $stage0c,  $templatePath);
        $this->debugDump('1-transformed', $stage1,   $templatePath);
        $this->debugDump('2-echoed',      $stage2,   $templatePath);
        $this->debugDump('3-compiled',    $compiled, $templatePath);

        // Сохранить скомпилированный PHP в кеш.
        $this->storeCompiledToCache($templatePath, $source, $compiled);

        // 4) Выполнение
        return $this->evaluate($compiled, $vars, $templatePath);
    }

    /**
     * Трансформация @extends, @section, @yield, @push, @stack, @include в PHP-код.
     * include и extends вызывают фабрику представлений, чтобы срабатывали композиторы.
     *
     * @param string $src Исходный текст шаблона.
     *
     * @return string Преобразованный текст.
     */
    private function transformLayoutsAndIncludes(string $src): string
    {
        // @extends('layouts.name')
        $src = preg_replace(
            '/@extends\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            "<?php \$__parent_view = '$1'; ?>",
            $src
        ) ?? $src;

        // @section('name') ... @endsection
        $src = preg_replace(
            '/@section\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            "<?php \$__section_stack[] = '$1'; ob_start(); ?>",
            $src
        ) ?? $src;

        $src = preg_replace(
            '/@endsection\b/',
            "<?php \$__sec = array_pop(\$__section_stack) ?? null; "
                . "if (\$__sec!==null) { \$__sections[\$__sec] = ob_get_clean(); } ?>",
            $src
        ) ?? $src;

        // @yield('name')
        $src = preg_replace(
            '/@yield\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            "<?= \$__sections['$1'] ?? '' ?>",
            $src
        ) ?? $src;

        // @push('name') ... @endpush
        $src = preg_replace(
            '/@push\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            "<?php \$__push_stack[] = '$1'; ob_start(); ?>",
            $src
        ) ?? $src;

        $src = preg_replace(
            '/@endpush\b/',
            "<?php \$__ps = array_pop(\$__push_stack) ?? null; "
                . "if (\$__ps!==null) { \$__stacks[\$__ps][] = ob_get_clean(); } ?>",
            $src
        ) ?? $src;

        // @stack('name')
        $src = preg_replace(
            '/@stack\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            "<?= isset(\$__stacks['$1']) ? implode('', \$__stacks['$1']) : '' ?>",
            $src
        ) ?? $src;

        // @include('view.name', args) с trim результата
        $src = preg_replace_callback(
            '/@include\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?)\s*)?\)/',
            function ($m) {
                $name = $m[1];
                $arg  = isset($m[2]) ? $m[2] : '[]';
                return "<?= (isset(\$__factory) && \$__factory) "
                    . "? trim((string)\$__factory->make('{$name}', "
                    . "array_merge(get_defined_vars(), (array)({$arg})))->render()) "
                    . ": '' ?>";
            },
            $src
        ) ?? $src;

        return $src;
    }

    /**
     * Безопасная компиляция двойных фигурных скобок в HTML-escaped echo.
     * Поддерживается dot-нотация: a.b.c превращается в a['b']['c'].
     *
     * @param string $src Исходный текст шаблона.
     *
     * @return string Преобразованный текст.
     */
    private function compileEcho(string $src): string
    {
        return preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function ($m) {
                $expr = $this->dotToArrayAccess($m[1]);
                return "<?= htmlspecialchars((string)(is_array(($expr)) ? '' : ($expr)), "
                    . "ENT_QUOTES, 'UTF-8') ?>";
            },
            $src
        ) ?? $src;
    }

    /**
     * Преобразует a.b.c в a['b']['c'] для простых идентификаторов.
     *
     * @param string $expr Выражение внутри двойных фигурных скобок.
     * @return string Преобразованное выражение.
     */
    private function dotToArrayAccess(string $expr): string
    {
        return preg_replace_callback(
            '/\$[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)+/',
            static function ($m) {
                $parts = explode('.', $m[0]);
                $base  = array_shift($parts);
                $acc   = $base;
                foreach ($parts as $p) {
                    $acc .= "['{$p}']";
                }
                return $acc;
            },
            $expr
        ) ?? $expr;
    }

    /**
     * Удаляет blade-комментарии до любых других стадий.
     * Полнострочные комментарии удаляются вместе с завершающим переводом строки.
     * Инлайновые комментарии удаляются без влияния на остальную строку.
     *
     * @param string $src Исходный текст.
     * @return string Текст без комментариев.
     */
    private function stripBladeComments(string $src): string
    {
        $src = preg_replace(
            '/^[ \t]*\{\{\-\-.*?\-\-\}\}[ \t]*(\r?\n)?/ms',
            '',
            $src
        ) ?? $src;
        $src = preg_replace('/\{\{\-\-.*?\-\-\}\}/s', '', $src) ?? $src;
        return $src;
    }

    /**
     * Удаляет пустые блоки push, если внутри только пробелы/табы/пусто.
     * Удаляется и завершающий перевод строки.
     *
     * @param string $src Исходный текст.
     * @return string Текст без пустых блоков push.
     */
    private function pruneEmptyPushBlocks(string $src): string
    {
        return preg_replace_callback(
            '/^[ \t]*@push\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)@endpush[ \t]*(?:\r?\n)?/ms',
            static function (array $m): string {
                return (preg_match('/\S/', $m[2]) === 1) ? $m[0] : '';
            },
            $src
        ) ?? $src;
    }

    /**
     * Удаляет строки, которые пустые или состоят только из пробелов/табов.
     *
     * @param string $src Исходный текст.
     * @return string Текст без пустых строк.
     */
    private function removeWhitespaceOnlyLines(string $src): string
    {
        return preg_replace('/^[ \t]*\r?\n/m', '', $src) ?? $src;
    }

    /**
     * Сохранить промежуточный или финальный код для диагностики.
     * Работает только если существует каталог /tmp/faravel_blade_cache.
     *
     * @param string $stage   Метка стадии.
     * @param string $code    Код.
     * @param string $tplPath Путь исходного шаблона или пустая строка.
     * @return void
     */
    private function debugDump(string $stage, string $code, string $tplPath): void
    {
        $dir = '/tmp/faravel_blade_cache';
        if (!is_dir($dir)) {
            return;
        }
        $name = $tplPath !== '' ? $tplPath : 'inline';
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'tpl';
        $file = rtrim($dir, '/') . '/' . $slug . '.' . $stage . '.php';
        @file_put_contents($file, $code);
    }

    /**
     * Выполнить скомпилированный PHP-код в изолированном скоупе.
     * При включенном флаге collapseEmptyLines удаляет пустые строки в конце.
     *
     * @param string              $compiled Скомпилированный PHP.
     * @param array<string,mixed> $vars     Данные представления.
     * @param string              $templatePath Путь к шаблону.
     * @return string Готовый HTML.
     *
     * @throws RuntimeException при ошибке исполнения.
     */
    private function evaluate(
        string $compiled,
        array $vars,
        string $templatePath
    ): string {
        $factory = $this->factory;

        ob_start();
        try {
            (static function () use ($compiled, $vars, $factory) {
                extract($vars, EXTR_SKIP);
                $__sections      = $__sections ?? [];
                $__stacks        = $__stacks   ?? [];
                $__section_stack = [];
                $__push_stack    = [];
                $__parent_view   = $__parent_view ?? null;
                $__factory       = $factory;

                eval('?>' . $compiled);

                if ($__parent_view && $__factory) {
                    echo $__factory->make(
                        $__parent_view,
                        array_merge(get_defined_vars(), ['__parent_view' => null])
                    )->render();
                }
            })();

            $out = (string) ob_get_clean();

            if ($this->collapseEmptyLines) {
                $out = preg_replace('/^[ \t]*\r?\n/m', '', $out) ?? $out;
            }

            return $out;
        } catch (\Throwable $e) {
            ob_end_clean();
            $hint = $templatePath !== '' ? " in template {$templatePath}" : '';
            throw new RuntimeException(
                'Blade render failed' . $hint . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Попытаться получить скомпилированный PHP из кеша.
     *
     * Логика:
     * - если задан путь шаблона, ключ строится по пути, mtime, размеру и версии;
     * - если путь пуст, используем хеш исходника и версии.
     *
     * @param string $templatePath Путь шаблона или пустая строка.
     * @param string $source       Исходный текст шаблона.
     * @return string|null Скомпилированный PHP или null, если нет в кеше.
     */
    private function fetchCompiledFromCache(
        string $templatePath,
        string $source
    ): ?string {
        $key = $this->makeCacheKey($templatePath, $source);
        if ($key === null) {
            return null;
        }
        /** @var mixed $val */
        $val = Cache::get($key);
        return is_string($val) ? $val : null;
    }

    /**
     * Сохранить скомпилированный PHP в кеш.
     *
     * @param string $templatePath Путь шаблона или пустая строка.
     * @param string $source       Исходный текст шаблона.
     * @param string $compiled     Скомпилированный PHP.
     *
     * Side effects:
     * - Запись в файловый кеш через Cache фасад.
     *
     * @return void
     */
    private function storeCompiledToCache(
        string $templatePath,
        string $source,
        string $compiled
    ): void {
        $key = $this->makeCacheKey($templatePath, $source);
        if ($key === null) {
            return;
        }
        Cache::put($key, $compiled, $this->cacheTtlSeconds);
    }

    /**
     * Построить ключ кеша для скомпилированного шаблона.
     *
     * Формула:
     * - при наличии реального файла: hash(path|mtime|size|sig|flags);
     * - иначе: hash(inline|md5(source)|sig|flags).
     *
     * @param string $templatePath Путь к файлу шаблона или пустая строка.
     * @param string $source       Исходный текст, если путь не задан.
     * @return string|null Ключ кеша или null.
     */
    private function makeCacheKey(
        string $templatePath,
        string $source
    ): ?string {
        $flags = ($this->strict ? 'S' : 's')
            . ($this->echoesOnlyVars ? 'E' : 'e')
            . ($this->disallowRawEcho ? 'R' : 'r');

        if ($templatePath !== '' && is_file($templatePath)) {
            $mtime = (string) @filemtime($templatePath);
            $size  = (string) @filesize($templatePath);
            $base  = $templatePath . '|' . $mtime . '|' . $size
                . '|' . self::COMPILE_SIG . '|' . $flags;
        } else {
            $base = 'inline|' . md5($source) . '|' . self::COMPILE_SIG . '|' . $flags;
        }

        return 'blade:' . md5($base);
    }

    /** Запрет inline PHP в шаблоне. */
    private function assertNoForbiddenInlinePhp(
        string $source,
        string $templatePath
    ): void {
        if (!$this->strict) {
            return;
        }
        if (
            str_contains($source, '<?php') ||
            str_contains($source, '<?=')   ||
            str_contains($source, '<?=')
        ) {
            $hint = $templatePath !== '' ? " in {$templatePath}" : '';
            throw new RuntimeException(
                'Inline PHP is forbidden in Blade templates' . $hint
            );
        }
    }

    /** Запрет директив php и endphp. */
    private function assertNoBladePhpBlocks(
        string $source,
        string $templatePath
    ): void {
        if (
            preg_match('/@php\b/i', $source) === 1 ||
            preg_match('/@endphp\b/i', $source) === 1
        ) {
            $hint = $templatePath !== '' ? " in {$templatePath}" : '';
            throw new RuntimeException(
                '@php/@endphp blocks are forbidden' . $hint
            );
        }
    }

    /** Мягкий доступ к конфигу: helper, фасад или дефолт. */
    private function configGetInt(string $key, int $default): int
    {
        $v = $this->configGet($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }
    private function configGetBool(string $key, bool $default): bool
    {
        $v = $this->configGet($key, $default);
        if (is_bool($v)) return $v;
        if (is_string($v)) return in_array(strtolower($v), ['1','true','yes','on'], true);
        if (is_numeric($v)) return ((int)$v) !== 0;
        return $default;
    }
    /** @param mixed $default @return mixed */
    private function configGet(string $key, $default)
    {
        try {
            if (function_exists('config')) {
                $val = config($key);
                return $val !== null ? $val : $default;
            }
        } catch (\Throwable $e) {
        }
        try {
            if (class_exists('\Faravel\Support\Config')) {
                return \Faravel\Support\Config::get($key, $default);
            }
        } catch (\Throwable $e) {
        }
        return $default;
    }

    /** Совместимость с фасадом и контейнером. */
    public function make(string $id): self
    {
        return $this;
    }
}
