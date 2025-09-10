<?php
/* app/Support/Abilities.php v0.1.0
Тонкий фасад над AbilityService для удобных вызовов в коде.
Назначение: единая точка обращения к проверкам способностей.
FIX: добавлены методы allows() и check() → прокси к AbilityService::has(). */

namespace App\Support;

use App\Services\Auth\AbilityService;

final class Abilities
{
    /** Синоним allows: возвращает bool. */
    public static function check(string $ability, ?array $user = null, $context = null): bool
    {
        return AbilityService::has($user, $ability, $context);
    }

    /** Читаемеее имя для контроллеров/экшенов. */
    public static function allows(string $ability, ?array $user = null, $context = null): bool
    {
        return AbilityService::has($user, $ability, $context);
    }
}
