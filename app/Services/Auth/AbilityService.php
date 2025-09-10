<?php // v0.3.32

namespace App\Services\Auth;

use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;

class AbilityService
{
    /** @var array<string,int> name => min_role */
    private static array $abilities = [];
    private static bool $abilitiesLoaded = false;

    /** @var array<int,string> roleId => roleName (для кеша справочника ролей) */
    private static array $roles = [];
    private static bool $rolesLoaded = false;

    /**
     * Главная проверка: достаточно ли роли пользователя для способности.
     * Поддержка *.own при наличии $context (user_id/owner_id/...).
     */
    public static function has(?array $user, string $ability, $context = null): bool
    {
        if (!$user) {
            $user = Auth::user(); // гость => null
        }
        $roleId = self::userRoleId($user);

        // banned < 0 — без прав
        if ($roleId < 0) {
            return false;
        }

        $map = self::abilitiesMap(); // name => min_role
        $required = $map[$ability] ?? null;
        if ($required === null) {
            return false; // неизвестная способность
        }

        if ($roleId < $required) {
            return false;
        }

        // Суффикс ".own": сверяем владельца
        if (str_ends_with($ability, '.own')) {
            $ownerId = self::extractOwnerId($context);
            $userId  = self::userId($user);
            if ($ownerId === null || $userId === null || $ownerId !== $userId) {
                return false;
            }
        }

        return true;
    }

    /** Разовая загрузка abilities из БД с кешем на процесс */
    public static function abilitiesMap(): array
    {
        if (!self::$abilitiesLoaded) {
            self::$abilities = [];
            try {
                $rows = DB::select("SELECT name, min_role FROM abilities");
                foreach ($rows as $r) {
                    $name = is_array($r) ? ($r['name'] ?? null) : null;
                    $min  = is_array($r) ? ($r['min_role'] ?? null) : null;
                    if ($name !== null && $min !== null) {
                        self::$abilities[$name] = (int) $min;
                    }
                }
            } catch (\Throwable $e) {
                self::$abilities = [];
            }
            self::$abilitiesLoaded = true;
        }
        return self::$abilities;
    }

    /** Разовая загрузка ролей (id,name) из БД, если нужно для UI/служебных задач */
    public static function roles(): array
    {
        if (!self::$rolesLoaded) {
            self::$roles = [];
            try {
                $rows = DB::select("SELECT id, name FROM roles");
                foreach ($rows as $r) {
                    $id = is_array($r) ? ($r['id'] ?? null) : null;
                    $nm = is_array($r) ? ($r['name'] ?? null) : null;
                    if ($id !== null && $nm !== null) {
                        self::$roles[(int)$id] = (string)$nm;
                    }
                }
            } catch (\Throwable $e) {
                self::$roles = [];
            }
            self::$rolesLoaded = true;
        }
        return self::$roles;
    }

    /** Инвалидация кешей (на случай админ-редактора) */
    public static function invalidate(): void
    {
        self::$abilitiesLoaded = false;
        self::$abilities = [];
        self::$rolesLoaded = false;
        self::$roles = [];
    }

    // --- helpers ---

    private static function userRoleId(?array $user): int
    {
        if (!$user) return 0; // гость
        if (isset($user['role_id'])) return (int)$user['role_id'];
        if (isset($user['role']))    return (int)$user['role'];
        return 0;
    }

    private static function userId(?array $user): ?int
    {
        if (!$user) return null;
        if (isset($user['id'])) return (int)$user['id'];
        if (isset($user['user_id'])) return (int)$user['user_id'];
        return null;
    }

    private static function extractOwnerId($context): ?int
    {
        if ($context === null) return null;

        if (is_array($context)) {
            foreach (['user_id','owner_id','author_id','uid','id'] as $k) {
                if (isset($context[$k])) return (int)$context[$k];
            }
        }
        if (is_object($context)) {
            foreach (['getUserId','getOwnerId','getAuthorId'] as $m) {
                if (method_exists($context, $m)) {
                    $v = $context->$m();
                    if ($v !== null) return (int)$v;
                }
            }
            foreach (['user_id','owner_id','author_id','uid','id'] as $p) {
                if (isset($context->$p)) return (int)$context->$p;
            }
        }
        return null;
    }
}
