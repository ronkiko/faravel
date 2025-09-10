<?php

namespace App\Support;

use Faravel\Support\Facades\Auth;

/**
 * ACL Gate:
 * - уровень = roles.id (−1..7, см. config/acl.php['roles']);
 * - итоговый набор способностей = объединение abilities из config('acl') от 0 до текущего уровня;
 * - уровень −1 (banned) НЕ наследует ничего;
 * - поддерживаются маски "prefix.*".
 *
 * Per-request API:
 *   Gate::init($level)   — зафиксировать Gate на время запроса
 *   Gate::current()      — получить Gate текущего запроса (или собрать из Auth)
 *   Gate::reset()        — сбросить текущий экземпляр
 */
final class Gate
{
    /** Текущий уровень (roles.id) */
    private int $level;

    /** @var array<string,true> Разрешённые абилки (нормализованные) */
    private array $granted = [];

    /** @var array<int,array<int,string>> abilities по уровням */
    private array $abilitiesByLevel = [];

    /** Текущий (per-request) Gate */
    private static ?self $current = null;

    public function __construct(int $level)
    {
        // Клэмпим уровень к min/max из списка ролей
        [$min, $max] = $this->boundsFromConfig();
        $lvl = (int)$level;
        if ($lvl < $min) $lvl = $min;
        if ($lvl > $max) $lvl = $max;
        $this->level = $lvl;

        $this->loadConfig();
        $this->composeGrantSet();
    }

    // ========= static per-request API =========

    public static function init(int $level): void
    {
        self::$current = new self($level);
    }

    public static function current(): self
    {
        if (self::$current instanceof self) {
            return self::$current;
        }
        return self::$current = self::fromAuth();
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    // =============== публичный API ===============

    public function level(): int
    {
        return $this->level;
    }

    public function allows(string $ability): bool
    {
        $ability = $this->norm($ability);
        if ($ability === '') return false;

        // 1) точное совпадение
        if (isset($this->granted[$ability])) return true;

        // 2) совпадение по маске: granted 'foo.*' покрывает 'foo.bar'
        foreach ($this->granted as $g => $_) {
            if ($this->isMask($g) && $this->maskCovers($g, $ability)) {
                return true;
            }
        }

        // 3) запрошена маска: 'foo.*' → ok, если есть любой granted с префиксом 'foo.'
        if ($this->isMask($ability)) {
            $pref = substr($ability, 0, -2);
            foreach ($this->granted as $g => $_) {
                if (strncmp($g, $pref . '.', strlen($pref) + 1) === 0) return true;
                if ($this->isMask($g) && $this->maskCovers($g, $pref . '.x')) return true;
            }
        }

        return false;
    }

    public function any(array $abilities): bool
    {
        foreach ($abilities as $a) {
            if ($this->allows((string)$a)) return true;
        }
        return false;
    }

    public function all(array $abilities): bool
    {
        foreach ($abilities as $a) {
            if (!$this->allows((string)$a)) return false;
        }
        return true;
    }

    /**
     * Gate из текущего Auth::user():
     * - гость → 0
     * - если в users.role_id = −1 → бан
     * - никакого автоподнятия 0→1 — берём ровно как в БД
     */
    public static function fromAuth(): self
    {
        $u = Auth::user();
        $lvl = (int)($u['role_id'] ?? 0);
        return new self($lvl);
    }

    // =============== internals ===============

    private function loadConfig(): void
    {
        $cfg = \config('acl.abilities', []);
        $this->abilitiesByLevel = is_array($cfg) ? $cfg : [];
    }

    private function composeGrantSet(): void
    {
        $grants = [];

        if ($this->level === -1) {
            // Забаненным НЕ наследуем гость/юзер и пр.
            foreach ($this->abilitiesByLevel[-1] ?? [] as $ab) {
                $ab = $this->norm((string)$ab);
                if ($ab !== '') $grants[$ab] = true;
            }
        } else {
            // Кумулятивно 0..N
            for ($i = 0; $i <= $this->level; $i++) {
                if (!empty($this->abilitiesByLevel[$i]) && is_array($this->abilitiesByLevel[$i])) {
                    foreach ($this->abilitiesByLevel[$i] as $ab) {
                        $ab = $this->norm((string)$ab);
                        if ($ab !== '') $grants[$ab] = true;
                    }
                }
            }
        }

        $this->granted = $grants;
    }

    private function norm(string $ability): string
    {
        $ability = strtolower(trim($ability));
        $ability = preg_replace('/[^a-z0-9_.*]/', '', $ability) ?? '';
        $ability = preg_replace('/\.+/', '.', $ability) ?? '';
        $ability = preg_replace('/\*+/', '*', $ability) ?? '';
        return $ability;
    }

    private function isMask(string $ab): bool
    {
        return str_ends_with($ab, '.*'); // для PHP <8: return substr($ab, -2) === '.*';
    }

    private function maskCovers(string $mask, string $ability): bool
    {
        if (!$this->isMask($mask)) return false;
        $prefix = substr($mask, 0, -2);
        if ($prefix === '') return true;
        if ($ability === $prefix) return true;
        return strncmp($ability, $prefix . '.', strlen($prefix) + 1) === 0;
    }

    /** @return array{0:int,1:int} [min,max] из config('acl.roles') */
    private function boundsFromConfig(): array
    {
        $roles = \config('acl.roles', []);
        if (!is_array($roles) || empty($roles)) {
            return [-1, 7];
        }
        $keys = array_map('intval', array_keys($roles));
        return [min($keys), max($keys)];
    }
}
