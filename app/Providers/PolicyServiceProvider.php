<?php // v0.4.1
/* app/Providers/PolicyServiceProvider.php
Purpose: Регистрирует DI-биндинги для policy-контрактов. Контроллеры зависят
от интерфейсов, а контейнер выдаёт реализации.
FIX: Новый провайдер. Добавлен bind() TopicPolicyContract → TopicPolicy.
*/
namespace App\Providers;

use Faravel\Foundation\ServiceProvider;
use App\Contracts\Policies\Forum\TopicPolicyContract;
use App\Policies\Forum\TopicPolicy;

final class PolicyServiceProvider extends ServiceProvider
{
    /**
     * Register policy bindings.
     *
     * Summary: объявляет правила создания реализаций для policy-контрактов.
     *
     * Preconditions:
     * - Контейнер приложения и автозагрузчик доступны.
     *
     * Side effects:
     * - Модифицирует состояние контейнера (реестр биндингов).
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(TopicPolicyContract::class, function () {
            return new TopicPolicy();
        });
    }

    /**
     * Boot stage is not used for policies now.
     *
     * @return void
     */
    public function boot(): void
    {
        // no-op
    }
}
