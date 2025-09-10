<?php

namespace Faravel\Auth;

use Faravel\Exceptions\AuthorizationException;

/**
 * Менеджер авторизации. Позволяет определять "способности" (abilities) через
 * замыкания (ворота) и назначать политики для моделей. Способности могут
 * проверяться для текущего пользователя или переданного пользователя.
 * Политики группируют методы авторизации по модели: если способность не
 * определена явно, менеджер попытается найти соответствующий метод в
 * зарегистрированной политике.
 */
class GateManager
{
    /**
     * Зарегистрированные closure‑ворота.
     *
     * @var array<string,callable>
     */
    protected array $abilities = [];

    /**
     * Карта моделей и их политик.
     *
     * @var array<string,string>
     */
    protected array $policies = [];

    /**
     * Сервис аутентификации, предоставляющий текущего пользователя.
     */
    protected Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Определить новое правило (ability) через closure. Closure должна
     * принимать пользователя первым аргументом, а затем произвольные
     * параметры.
     *
     * @param string $ability
     * @param callable $callback
     * @return void
     */
    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * Привязать класс модели к политике.
     *
     * @param string $modelClass
     * @param string $policyClass
     */
    public function policy(string $modelClass, string $policyClass): void
    {
        $this->policies[$modelClass] = $policyClass;
    }

    /**
     * Проверить, разрешена ли способность текущему пользователю.
     *
     * @param string $ability
     * @param mixed $arguments
     * @return bool
     */
    public function allows(string $ability, mixed $arguments = null): bool
    {
        $user = $this->auth->user();
        return $this->check($ability, $user, $arguments);
    }

    /**
     * Проверить, запрещена ли способность текущему пользователю.
     *
     * @param string $ability
     * @param mixed $arguments
     * @return bool
     */
    public function denies(string $ability, mixed $arguments = null): bool
    {
        return ! $this->allows($ability, $arguments);
    }

    /**
     * Проверить способность для конкретного пользователя.
     *
     * @param string $ability
     * @param \Faravel\Auth\Auth|\App\Models\User|null $user
     * @param mixed $arguments
     * @return bool
     */
    public function forUser($user): self
    {
        $clone = clone $this;
        // подменяем пользователя
        $clone->auth = new class($user) extends Auth {
            private $forcedUser;
            public function __construct($user)
            {
                $this->forcedUser = $user;
            }
            public function user(): ?array
            {
                return $this->forcedUser;
            }
        };
        return $clone;
    }

    /**
     * Пытается авторизовать и бросает исключение, если пользователь не
     * авторизован.
     *
     * @param string $ability
     * @param mixed $arguments
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed $arguments = null): void
    {
        if (! $this->allows($ability, $arguments)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * Внутренняя проверка способности.
     *
     * @param string $ability
     * @param mixed $user
     * @param mixed $arguments
     * @return bool
     */
    protected function check(string $ability, $user, mixed $arguments = null): bool
    {
        // Сначала ищем явно определённые ворота
        if (isset($this->abilities[$ability])) {
            $callback = $this->abilities[$ability];
            return (bool) call_user_func($callback, $user, $arguments);
        }
        // Если аргумент — объект или имя класса, ищем политику
        if ($arguments) {
            $class = is_object($arguments) ? get_class($arguments) : $arguments;
            if (isset($this->policies[$class])) {
                $policyClass = $this->policies[$class];
                if (class_exists($policyClass)) {
                    $policy = new $policyClass;
                    if (method_exists($policy, $ability)) {
                        return (bool) $policy->$ability($user, $arguments);
                    }
                }
            }
        }
        // По умолчанию запрещаем
        return false;
    }
}