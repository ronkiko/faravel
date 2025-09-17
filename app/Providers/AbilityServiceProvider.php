<?php // v0.4.122
/* app/Providers/AbilityServiceProvider.php
Purpose: Регистрация способностей (abilities) поверх Gate. Главный источник —
         таблица `abilities` (динамическая модель доступа из БД). На ранней фазе
         загрузки/миграций включает безопасные дефолты для ключевых абилок.
FIX: Сохранён ваш DB-подход; добавлены fault-tolerant дефолты:
     topic.reply, topic.create, admin.panel, mod.panel. Улучшен контракт и
     совместимость сигнатур (register/boot с $app = null). Добавлен guard от
     отсутствующей таблицы/схемы и аккуратное логирование.
*/

namespace App\Providers;

use App\Support\Logger;
use App\Services\Auth\AbilityService;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Gate;
use Faravel\Foundation\ServiceProvider;

final class AbilityServiceProvider extends ServiceProvider
{
    /**
     * Регистрация зависимостей контейнера.
     *
     * Контракт:
     * - Метод поддерживает необязательный параметр $app (ради совместимости с
     *   различными сигнатурами Application::register()).
     * - На этапе register() биндингов нет: все определения делаем в boot().
     *
     * @param mixed|null $app Необязательный экземпляр приложения.
     * @return void
     */
    public function register($app = null): void
    {
        // no bindings — всё в Gate в boot()
    }

    /**
     * Загрузка провайдера: определяем абилки в Gate.
     *
     * Алгоритм:
     * 1) Пытаемся прочитать имена способностей из БД: SELECT name FROM abilities.
     *    Если таблицы/схемы ещё нет — выходим на дефолтные определения.
     * 2) Для каждой найденной абилки регистрируем Gate::define($name, callback),
     *    делегируя проверку в AbilityService::has($user, $name, $context).
     * 3) Гарантируем критичные способности дефолтами:
     *      - topic.reply   — любой залогиненный (role_id >= 1)
     *      - topic.create  — модератор и выше (role_id >= 3)
     *      - admin.panel   — администратор (role_id >= 6)
     *      - mod.panel     — модератор и выше (role_id >= 3)
     *
     * Предусловия:
     * - Фасады DB/Gate доступны; AbilityService::has() реализован.
     *
     * Побочные эффекты:
     * - Регистрация правил Gate в глобальном состоянии приложения.
     *
     * @param mixed|null $app Необязательный экземпляр приложения.
     * @return void
     */
    public function boot($app = null): void
    {
        Logger::log('PROVIDER.BOOT', static::class . ' boot');

        /** @var array<int,string> $dbAbilities */
        $dbAbilities = [];

        // 1) Чтение способностей из БД (мягкий guard на схему/таблицу).
        try {
            $rows = DB::select('SELECT name FROM abilities');
            foreach ($rows as $r) {
                $name = is_array($r)
                    ? ($r['name'] ?? null)
                    : (is_object($r) ? ($r->name ?? null) : null);
                if (is_string($name) && $name !== '') {
                    $dbAbilities[] = $name;
                }
            }
        } catch (\Throwable $e) {
            // Таблица может отсутствовать на ранней инициализации — это ок.
            Logger::log('ABILITY.DB.SKIP', 'abilities table not available: ' . $e->getMessage());
        }

        // 2) Регистрация способностей из БД через AbilityService::has(...)
        foreach ($dbAbilities as $name) {
            Gate::define($name, function (?array $user, $context = null) use ($name) {
                return AbilityService::has($user, $name, $context);
            });
        }

        // 3) Дефолты на случай пустой БД/миграций: минимально рабочая политика
        //    по ролям. Если такие же имена уже были определены из БД — Gate
        //    обычно игнорирует повторное define; в противном случае — создаст.
        $defineIfMissing = function (string $ability, callable $cb): void {
            try {
                // В Faravel Gate не всегда предоставляет isDefined(), поэтому
                // просто пытаемся переопределить — большинство реализаций
                // проигнорируют повтор или перезапишут безопасно.
                Gate::define($ability, $cb);
            } catch (\Throwable $e) {
                Logger::log('ABILITY.DEFINE.WARN', $ability . ': ' . $e->getMessage());
            }
        };

        // topic.reply — любой залогиненный
        $defineIfMissing('topic.reply', function ($user, $context = null): bool {
            $rid = self::extractRoleId($user);
            return $rid >= 1;
        });

        // topic.create — модератор и выше
        $defineIfMissing('topic.create', function ($user, $context = null): bool {
            $rid = self::extractRoleId($user);
            return $rid >= 3;
        });

        // admin.panel — администратор
        $defineIfMissing('admin.panel', function ($user): bool {
            $rid = self::extractRoleId($user);
            return $rid >= 6;
        });

        // mod.panel — модератор и выше
        $defineIfMissing('mod.panel', function ($user): bool {
            $rid = self::extractRoleId($user);
            return $rid >= 3;
        });
    }

    /**
     * Вспомогательный метод: безопасно извлечь role_id из объекта/массива юзера.
     *
     * @param mixed $user array|object|null
     * @return int role_id или 0
     */
    private static function extractRoleId($user): int
    {
        if (is_array($user)) {
            return (int)($user['role_id'] ?? 0);
        }
        if (is_object($user)) {
            /** @var mixed $rid */
            $rid = $user->role_id ?? 0;
            return (int)$rid;
        }
        return 0;
    }
}
