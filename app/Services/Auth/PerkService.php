<?php // v0.1.0

namespace App\Services\Auth;

use Faravel\Support\Facades\DB;

class PerkService
{
    /** @var array<string,array> */
    protected static ?array $cache = null;

    /** Сброс кеша */
    public static function invalidate(): void
    {
        self::$cache = null;
    }

    /** Загрузка словаря (кешируется) */
    protected static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = DB::select("SELECT `key`, `label`, `description`, `min_group_id` FROM `perks`");
        $map  = [];
        foreach ($rows as $r) {
            $key = is_array($r) ? ($r['key'] ?? '') : ($r->key ?? '');
            if ($key !== '') {
                $map[$key] = [
                    'label'        => is_array($r) ? ($r['label'] ?? null) : ($r->label ?? null),
                    'description'  => is_array($r) ? ($r['description'] ?? null) : ($r->description ?? null),
                    'min_group_id' => (int) (is_array($r) ? ($r['min_group_id'] ?? 0) : ($r->min_group_id ?? 0)),
                ];
            }
        }
        return self::$cache = $map;
    }

    /** Доступность перка для пользователя */
    public static function unlocked($user, string $key): bool
    {
        $perks = self::all();
        if (!isset($perks[$key])) { return false; }
        $needGroup = (int) $perks[$key]['min_group_id'];
        $userGroup = (int) ($user['group_id'] ?? 0); // допускаем массив-подобный User
        return $userGroup >= $needGroup;
    }

    /** Получить метаданные перка */
    public static function get(string $key): ?array
    {
        $perks = self::all();
        return $perks[$key] ?? null;
    }
}
