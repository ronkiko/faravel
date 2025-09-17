<?php // v0.4.1
/* app/Contracts/Controller/InvokableController.php
Purpose: Базовый контракт для вызываемых контроллеров (Actions).
         Определяет унифицированную сигнатуру метода __invoke, который должен
         принимать объект запроса и дополнительные параметры маршрута и
         возвращать HTTP‑ответ. Реализуя этот контракт, контроллеры
         определяют свой публичный API и упрощают навигацию по коду.
FIX: Новый контракт введён для стандартизации интерфейсов контроллеров.
*/

namespace App\Contracts\Controller;

use Faravel\Http\Request;
use Faravel\Http\Response;

/**
 * Contract for invokable controllers (action classes).
 *
 * Any controller that defines a single `__invoke` handler should implement
 * this interface to clearly communicate its expected input and output. The
 * `$args` parameter covers route placeholders (e.g. `{id}`), ensuring
 * compatibility with Faravel's router.
 */
interface InvokableController
{
    /**
     * Handle the incoming request and route parameters.
     *
     * Preconditions: $request is a valid Faravel HTTP request. Additional
     * route parameters are passed in order of appearance in the route
     * definition (strings, numbers, etc.).
     * Side effects: may call services, interact with DB, flash session data
     * or set headers/cookies via Response.
     *
     * @param Request $request HTTP‑запрос.
     * @param mixed ...$args Маршрутные параметры (slug, id и др.).
     * @return Response HTTP‑ответ, который будет отправлен ядром.
     */
    public function __invoke(Request $request, mixed ...$args): Response;
}