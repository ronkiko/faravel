<?php // v0.3.37

namespace App\Providers;

use App\Services\Auth\AbilityService;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Gate;

// ВАЖНО: базовый класс провайдера должен совпадать с тем, что используется у тебя в проекте.
// В большинстве ваших провайдеров это, как правило, \Faravel\Foundation\ServiceProvider.
// Если у тебя другой базовый класс — просто замени use/extends на такой же, как в AppServiceProvider.
use Faravel\Foundation\ServiceProvider;

class AbilityServiceProvider extends ServiceProvider
{
    /**
     * В Faravel ваш Application::register(...) может передавать $app в register()/boot().
     * Делаем параметры необязательными, чтобы не ловить ArgumentCountError при несовпадении сигнатур.
     */
    public function register($app = null): void
    {
        // Ничего не биндируем — вся логика в Gate на этапе boot().
    }

    public function boot($app = null): void
    {
        // Пробуем прочитать список способностей. Если таблицы ещё нет — просто выходим.
        try {
            $rows = DB::select("SELECT name FROM abilities");
        } catch (\Throwable $e) {
            return;
        }

        foreach ($rows as $r) {
            // Поддержим и массивы, и объекты в ответе DB::select
            $name = is_array($r) ? ($r['name'] ?? null) : (is_object($r) ? ($r->name ?? null) : null);
            if (!$name) {
                continue;
            }

            Gate::define($name, function (?array $user, $context = null) use ($name) {
                return AbilityService::has($user, $name, $context);
            });
        }
    }
}
