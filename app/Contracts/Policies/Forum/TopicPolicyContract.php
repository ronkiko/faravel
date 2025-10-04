<?php // v0.4.1
/* app/Contracts/Policies/Forum/TopicPolicyContract.php
Purpose: Контракт политики доступа к теме форума. Нужен для инверсии зависимостей:
контроллеры/экшены зависят от интерфейса, реализация подменяема через DI.
FIX: Новый файл. Введён контракт canReply() для внедрения через контейнер и тестируемости.
*/
namespace App\Contracts\Policies\Forum;

/**
 * Interface TopicPolicyContract
 *
 * Контракт уровня Policies. Применяется из контроллеров/экшенов для проверки
 * права пользователя отвечать в теме. Скрывает детали реализации и источники
 * правил (БД/конфиг/внешняя служба).
 */
interface TopicPolicyContract
{
    /**
     * Проверить, может ли пользователь отвечать в указанной теме.
     *
     * Contract: метод не выполняет побочных эффектов и I/O.
     * Допустимы только чтения инжектированных зависимостей реализации.
     *
     * @param array|null         $user   Ассоциативный массив пользователя
     *                                   или null для гостя.
     * @param array|object       $topic  Тема как массив или объект с id/owner_id.
     *
     * Preconditions:
     * - $topic должен содержать идентификатор (id) или эквивалент.
     *
     * Side effects:
     * - Нет.
     *
     * @return bool              true, если разрешено; иначе false.
     *
     * @example
     *  $ok = $policy->canReply(['id'=>10,'role'=>2], ['id'=>55]);
     */
    public function canReply(?array $user, array|object $topic): bool;
}
