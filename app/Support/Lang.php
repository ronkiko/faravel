<?php

namespace App\Support;

/**
 * Простой переводчик с fallback:
 * - текущая локаль + резервная (по умолчанию en);
 * - файлы вида resources/lang/{locale}/{file}.php → возвращают массив;
 * - ключ "layout.nav.login" → файл layout.php, путь "nav.login";
 * - плейсхолдеры вида ":name".
 */
final class Lang
{
    private string $locale;
    private string $fallback;
    /** @var array<string,array<string,mixed>> */
    private array $cache = [];
    /** @var array<int,string> */
    private array $paths;

    /**
     * @param array<int,string> $paths Список корней с языковыми файлами
     */
    public function __construct(
        string $locale = 'ru',
        string $fallback = 'en',
        array $paths = []
    ) {
        $this->locale   = $this->sanitize($locale);
        $this->fallback = $this->sanitize($fallback);
        $this->paths    = $paths ?: [base_path('resources/lang')];
    }

    public function getLocale(): string   { return $this->locale; }
    public function getFallback(): string { return $this->fallback; }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->sanitize($locale);
    }

    public function setFallback(string $fallback): void
    {
        $this->fallback = $this->sanitize($fallback);
    }

    /**
     * Перевод по ключу. Возвращает сам ключ, если не найден
     * ни в текущей локали, ни во fallback.
     *
     * @param array<string,scalar> $repl
     */
    public function trans(string $key, array $repl = []): string
    {
        [$file, $path] = $this->splitKey($key);

        // 1) текущая локаль
        $line = $this->fetch($this->locale, $file, $path);

        // 2) fallback
        if ($line === null && $this->fallback !== $this->locale) {
            $line = $this->fetch($this->fallback, $file, $path);
        }

        $text = $line ?? $key;

        // плейсхолдеры вида ":name"
        if ($repl) {
            foreach ($repl as $k => $v) {
                $text = str_replace(':' . $k, (string)$v, $text);
            }
        }
        return $text;
    }

    /**
     * Упрощённый plural:
     *   ru/uk/be — формы: zero/one/few/many
     *   en — one/other
     * Если конкретная форма не найдена — пытаемся базовый ключ.
     *
     * @param array<string,scalar> $repl
     */
    public function choice(string $key, int $count, array $repl = []): string
    {
        $form = $this->pluralForm($count, $this->locale);
        $line = $this->trans($key . '.' . $form, array_merge($repl, ['count' => $count]));
        if ($line === $key . '.' . $form) {
            $line = $this->trans($key, array_merge($repl, ['count' => $count]));
        }
        return $line;
    }

    // ===== internals =====

    private function pluralForm(int $n, string $locale): string
    {
        $l = substr($locale, 0, 2);
        if (in_array($l, ['ru','uk','be'], true)) {
            $n = abs($n) % 100;
            $n1 = $n % 10;
            if ($n === 0) return 'zero';
            if ($n1 === 1 && $n !== 11) return 'one';
            if ($n1 >= 2 && $n1 <= 4 && ($n < 10 || $n >= 20)) return 'few';
            return 'many';
        }
        return $n === 1 ? 'one' : 'other';
    }

    /** @return array{0:string,1:string} */
    private function splitKey(string $key): array
    {
        $parts = explode('.', $key);
        $file  = $parts[0] ?? 'layout';
        $path  = implode('.', array_slice($parts, 1));
        if ($path === '') $path = 'value';
        return [$file, $path];
    }

    private function sanitize(string $s): string
    {
        $s = strtolower(trim($s));
        return preg_replace('/[^a-z0-9_-]/', '', $s) ?: 'en';
    }

    private function fetch(string $locale, string $file, string $path): ?string
    {
        $dict = $this->loadFile($locale, $file);
        $node = $dict;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) return null;
            $node = $node[$seg];
        }
        return is_scalar($node) ? (string)$node : null;
    }

    /** @return array<string,mixed> */
    private function loadFile(string $locale, string $file): array
    {
        $key = $locale . '::' . $file;
        if (isset($this->cache[$key])) return $this->cache[$key];

        foreach ($this->paths as $root) {
            $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $file . '.php';
            if (is_file($path)) {
                $arr = @include $path;
                return $this->cache[$key] = (is_array($arr) ? $arr : []);
            }
        }
        return $this->cache[$key] = [];
    }
}
