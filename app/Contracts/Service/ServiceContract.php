<?php // v0.4.1
/* app/Contracts/Service/ServiceContract.php
Purpose: Контракт для сервисов доменной логики. Он определяет единый
         метод `execute`, который принимает именованные параметры и
         возвращает результат обработки. Такой контракт позволяет
         контроллерам и другим слоям упрощённо работать с сервисами,
         а также облегчает тестирование и подмену реализаций.
FIX: Добавлен новый базовый интерфейс для всех сервисов в проекте.
*/

namespace App\Contracts\Service;

/**
 * Base interface for application services.
 *
 * Services encapsulate business logic and are typically called from
 * controllers. They should not perform rendering or output; instead,
 * return data structures or view models ready for presentation.
 */
interface ServiceContract
{
    /**
     * Execute the service with the provided parameters.
     *
     * Preconditions: $params contains validated inputs required by the
     * service (e.g. IDs, payload arrays). Each service should document
     * expected keys in PHPDoc or its contract.
     * Side effects: may read/write from the database, dispatch events,
     * or interact with external systems.
     *
     * @param array<string,mixed> $params
     * @return mixed Результат выполнения (VM, массив данных или void).
     */
    public function execute(array $params);
}