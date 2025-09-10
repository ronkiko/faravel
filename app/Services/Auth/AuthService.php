<?php // v0.4.1
/* app/Services/Auth/AuthService.php
Назначение: простой сервис аутентификации Faravel для текущей сессии; предоставляет
методы check()/user()/id()/login()/logout() для использования в middleware и политиках.
FIX: сервис переписан на зависимость Faravel\Http\Session (без Request::getSession()).
*/

namespace App\Services\Auth;

use Faravel\Http\Session;
use Faravel\Support\Facades\DB;

/**
 * Лёгкий auth-сервис поверх сессии и таблицы users.
 *
 * Слой: Service. Инкапсулирует «кто сейчас залогинен» без обращения к Blade/middleware.
 */
final class AuthService
{
    /** @var Session */
    private Session $session;

    /** @var array<string,mixed>|null Кэш текущего пользователя */
    private ?array $cachedUser = null;

    /** @var string Ключ в сессии для хранения id пользователя */
    private string $sessionKey = 'auth_user_id';

    /**
     * @param Session $session Экземпляр сессионного сервиса.
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Пользователь авторизован?
     *
     * @return bool true, если в сессии есть валидный user_id.
     */
    public function check(): bool
    {
        return $this->id() !== null;
    }

    /**
     * Гость?
     *
     * @return bool true, если пользователь не авторизован.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Идентификатор текущего пользователя.
     *
     * @return string|null UUID/строковый id либо null.
     */
    public function id(): ?string
    {
        $sid = $this->session->get($this->sessionKey);
        return is_string($sid) && $sid !== '' ? $sid : null;
    }

    /**
     * Текущий пользователь (ленивое чтение из БД с кэшем).
     *
     * Побочные эффекты: выполняет SELECT при первом вызове.
     *
     * @return array<string,mixed>|null Ассоц. массив полей пользователя или null.
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }
        $id = $this->id();
        if ($id === null) {
            return null;
        }
        try {
            $row = DB::table('users')->where('id', '=', $id)->first();
        } catch (\Throwable) {
            return null;
        }
        if (!$row) {
            return null;
        }
        return $this->cachedUser = (array)$row;
    }

    /**
     * Залогинить пользователя по id (проверка пароля вне зоны ответственности).
     *
     * @param string $userId Существующий в БД id пользователя.
     * @return void
     */
    public function login(string $userId): void
    {
        $this->session->put($this->sessionKey, $userId);
        $this->cachedUser = null;
    }

    /**
     * Разлогинить пользователя.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->forget($this->sessionKey);
        $this->cachedUser = null;
    }
}
