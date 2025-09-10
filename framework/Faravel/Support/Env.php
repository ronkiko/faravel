<?php

namespace Faravel\Support;

/**
 * Простая реализация загрузчика переменных окружения из файла .env.
 */
class Env
{
    /** @var bool Метка, что файл .env уже загружен */
    protected static bool $loaded = false;

    /**
     * Загружает файл .env из указанного каталога. Повторные вызовы игнорируются.
     *
     * @param string $basePath Каталог, где расположен .env
     */
    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }
        $envFile = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!file_exists($envFile)) {
            // Нет файла .env — пропускаем
            self::$loaded = true;
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Игнорируем комментарии
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Удаляем кавычки вокруг значения
            if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            putenv($key . '=' . $value);
        }
        self::$loaded = true;
    }

    /**
     * Получить переменную окружения.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}