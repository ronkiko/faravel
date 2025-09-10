<?php // v0.4.2
/* framework/Faravel/Cache/Cache.php
Purpose: файловый кеш Faravel с простым API get/put/forget. Автоматически
создаёт каталог кеша и при недоступности storage/cache переключается на
безопасный каталог во временной директории.
FIX: Добавлена ensureWritableDir с fallback на /tmp/faravel_cache, атомарная
запись во временный файл + rename, мягкая обработка прав и отсутствие каталога.
*/

namespace Faravel\Cache;

/**
 * Простой файловый кеш.
 * Формат файла: сериализованный массив {e:int, v:mixed}, где e — unix timestamp
 * истечения (0 — без истечения), v — значение.
 */
final class Cache
{
    /** @var string|null Фактический каталог для записи кеша. */
    private static ?string $dir = null;

    /**
     * Получить значение из кеша по ключу.
     *
     * @param string $key Ключ.
     * @param mixed  $default Дефолт, если нет в кеше или истекло.
     *
     * Preconditions:
     * - $key не пустой.
     *
     * @return mixed Значение или $default.
     */
    public static function get(string $key, $default = null)
    {
        $file = self::filePath($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('v', $payload)) {
            return $default;
        }
        $e = (int)($payload['e'] ?? 0);
        if ($e > 0 && $e < time()) {
            @unlink($file);
            return $default;
        }
        return $payload['v'];
    }

    /**
     * Положить значение в кеш по ключу.
     *
     * @param string $key   Ключ.
     * @param mixed  $value Значение.
     * @param int    $ttl   Время жизни в секундах (0 — без истечения).
     *
     * Preconditions:
     * - $key не пустой, $ttl >= 0.
     *
     * Side effects:
     * - Запись файла в каталог кеша.
     *
     * @return void
     */
    public static function put(string $key, $value, int $ttl = 0): void
    {
        $dir = self::dir();
        $file = self::filePath($key);

        $exp = $ttl > 0 ? (time() + $ttl) : 0;
        $payload = ['e' => $exp, 'v' => $value];
        $data = serialize($payload);

        // Атомарная запись: tmp → rename
        $tmp = @tempnam($dir, 'w_');
        if ($tmp === false) {
            // Последний шанс: пишем напрямую (может вызвать warning, но пробуем)
            @file_put_contents($file, $data);
            return;
        }
        @file_put_contents($tmp, $data);
        @chmod($tmp, 0666);
        @rename($tmp, $file);
    }

    /**
     * Удалить ключ из кеша.
     *
     * @param string $key Ключ.
     * @return void
     */
    public static function forget(string $key): void
    {
        $file = self::filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Полный путь к файлу по ключу.
     *
     * @param string $key Ключ.
     * @return string Путь к .cache файлу.
     */
    private static function filePath(string $key): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $key) ?? md5($key);
        return rtrim(self::dir(), '/') . '/' . $safe . '.cache';
    }

    /**
     * Вычислить каталог кеша с авто-созданием и fallback.
     *
     * Логика:
     * - Пытаемся использовать config('cache.path') если есть.
     * - Иначе <project_root>/storage/cache.
     * - Если каталог недоступен для записи, fallback в /tmp/faravel_cache.
     *
     * @return string Доступный для записи каталог.
     */
    private static function dir(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        $base = self::configGetString('cache.path', '');
        if ($base === '') {
            // Определяем корень проекта из текущего расположения файла.
            $root = dirname(__DIR__, 3); // .../framework → /var/www/html
            $base = $root . '/storage/cache';
        }

        $dir = self::ensureWritableDir($base);
        if ($dir === null) {
            $fallback = rtrim(sys_get_temp_dir(), '/') . '/faravel_cache';
            $dir = self::ensureWritableDir($fallback);
        }
        if ($dir === null) {
            // Совсем крайний случай: возвращаем исходный base, пусть ОС решает.
            $dir = $base;
        }

        self::$dir = $dir;
        return self::$dir;
    }

    /**
     * Убедиться, что каталог существует и доступен для записи.
     *
     * @param string $path Путь к каталогу.
     * @return string|null Путь, если ок; иначе null.
     */
    private static function ensureWritableDir(string $path): ?string
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        if (is_dir($path) && is_writable($path)) {
            return $path;
        }
        return null;
    }

    /**
     * Мягкое чтение строки из конфига (если поддерживается).
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    private static function configGetString(string $key, string $default): string
    {
        try {
            if (function_exists('config')) {
                $val = config($key);
                return is_string($val) && $val !== '' ? $val : $default;
            }
        } catch (\Throwable $e) {
        }
        try {
            if (class_exists('\Faravel\Support\Config')) {
                $val = \Faravel\Support\Config::get($key, $default);
                return is_string($val) && $val !== '' ? $val : $default;
            }
        } catch (\Throwable $e) {
        }
        return $default;
    }
}
