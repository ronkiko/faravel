<?php

namespace Faravel\Support;

class Config
{
    protected static array $items = [];

    public static function load(string $configDir): void
    {
        foreach (glob($configDir . '/*.php') as $file) {
            $key = basename($file, '.php');

            // Защита: загружаем только если это файл, и он начинается с <?php return
            $content = file_get_contents($file);

            if (!preg_match('/^\s*<\?php\s+return\s+/s', $content)) {
                throw new \Exception("Файл конфигурации должен начинаться с '<?php return [...]': $file");
            }

            $data = include $file;

            if (!is_array($data)) {
                throw new \Exception("Файл конфигурации '$file' не вернул массив");
            }

            self::$items[$key] = $data;
        }
    }


    /**
     * Получает значение по ключу с точечной нотацией.
     */
    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Устанавливает значение по ключу с точечной нотацией.
     */
    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /**
     * Проверяет, существует ли ключ.
     */
    public static function has(string $key): bool
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Возвращает все конфиги.
     */
    public static function all(): array
    {
        return self::$items;
    }

    /**
     * Добавляет значение в начало массива по ключу.
     */
    public static function prepend(string $key, $value): void
    {
        $array = self::get($key, []);

        if (!is_array($array)) {
            $array = [];
        }

        array_unshift($array, $value);
        self::set($key, $array);
    }

    /**
     * Добавляет значение в конец массива по ключу.
     */
    public static function push(string $key, $value): void
    {
        $array = self::get($key, []);

        if (!is_array($array)) {
            $array = [];
        }

        $array[] = $value;
        self::set($key, $array);
    }
}
