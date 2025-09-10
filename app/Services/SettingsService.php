<?php

namespace App\Services;

use Faravel\Support\Facades\DB;

/**
 * Key-value настройки в таблице `settings`.
 * Важно: столбец называется `key` (зарезервированное слово в MySQL),
 * поэтому используем низкоуровневый SQL с бэктиками вокруг имён столбцов.
 */
class SettingsService
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $pdo = DB::connection(); // PDO
            $stmt = $pdo->prepare("SELECT `value` FROM `settings` WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('value', $row)) {
                return self::$cache[$key] = $row['value'];
            }
        } catch (\Throwable $e) {
            // Логгировать по желанию: error_log('[SettingsService.get] '.$e->getMessage());
        }

        return $default;
    }

    public static function getInt(string $key, int $default, int $min, int $max): int
    {
        $val = self::get($key, $default);
        $num = is_numeric($val) ? (int)$val : $default;
        if ($num < $min) $num = $min;
        if ($num > $max) $num = $max;
        return $num;
    }

    public static function set(string $key, $value): void
    {
        $now = date('Y-m-d H:i:s');
        try {
            $pdo = DB::connection();

            // upsert по первичному ключу (`key`)
            $sql = "INSERT INTO `settings` (`key`, `value`, `created_at`, `updated_at`)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = VALUES(`updated_at`)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$key, (string)$value, $now, $now]);

            self::$cache[$key] = (string)$value;
        } catch (\Throwable $e) {
            // Логгировать по желанию: error_log('[SettingsService.set] '.$e->getMessage());
        }
    }
}
