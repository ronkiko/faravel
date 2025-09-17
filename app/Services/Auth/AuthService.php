<?php // v0.4.2
/* app/Services/Auth/AuthService.php
Purpose: простой сервис аутентификации Faravel для текущей сессии; предоставляет
методы check()/user()/id()/login()/logout() для использования в middleware и политиках.
FIX: Добавлен пер-запросный кэш (уже был) + TTL-кэш через Cache::remember() для user().
     В конфиг вынесен TTL (auth.user_cache_ttl). Добавлены методы invalidateUserCache()
     и buildUserCacheKey(). id() стал толерантен к int (приведение к string).
*/

namespace App\Services\Auth;

use Faravel\Http\Session;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Cache;

/**
 * Лёгкий auth-сервис поверх сессии и таблицы users.
 *
 * Слой: Service. Инкапсулирует «кто сейчас залогинен» без обращения к Blade/middleware.
 * Источник истины — БД. Для производительности: внутренний кэш запроса + короткий TTL-кэш.
 */
final class AuthService
{
    /** @var Session */
    private Session $session;

    /** @var array<string,mixed>|null Per-request cache of current user row */
    private ?array $cachedUser = null;

    /** @var string Session key to store only the current user's ID */
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
        // Be tolerant to legacy integer IDs in session.
        if (is_int($sid)) {
            $sid = (string) $sid;
        }
        return is_string($sid) && $sid !== '' ? $sid : null;
    }

    /**
     * Текущий пользователь.
     *
     * Сначала используется кэш в пределах запроса ($this->cachedUser).
     * При его отсутствии выполняется Cache::remember() c коротким TTL, чтобы
     * избежать повторных SELECT между запросами. Истечёт TTL — данные обновятся.
     *
     * @pre Пользователь должен быть авторизован (id() !== null).
     * @side-effects При промахе кэша выполняется SELECT из БД и запись в кэш.
     * @return array<string,mixed>|null Ассоц. массив полей пользователя или null (гость/ошибка).
     * @throws void
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

        $ttl = (int) (config('auth.user_cache_ttl', 60) ?? 60);
        $key = $this->buildUserCacheKey($id);

        try {
            /** @var array<string,mixed>|null $row */
            $row = Cache::remember($key, $ttl, function () use ($id) {
                $dbRow = DB::table('users')->where('id', '=', $id)->first();
                return $dbRow ? (array) $dbRow : null;
            });
        } catch (\Throwable) {
            // На случай проблем с кэшем — мягкая деградация: читаем напрямую.
            try {
                $dbRow = DB::table('users')->where('id', '=', $id)->first();
                $row = $dbRow ? (array) $dbRow : null;
            } catch (\Throwable) {
                $row = null;
            }
        }

        if (!is_array($row)) {
            return null;
        }

        return $this->cachedUser = $row;
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
        $this->cachedUser = null; // сброс кэша запроса; TTL-кэш обновится на следующем чтении
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

    /**
     * Принудительно забыть TTL-кэш пользователя (после изменения его данных).
     *
     * @param string $id Идентификатор пользователя, чей кэш нужно сбросить.
     * @return void
     */
    public function invalidateUserCache(string $id): void
    {
        try {
            Cache::forget($this->buildUserCacheKey($id));
        } catch (\Throwable) {
            // best-effort: игнорируем ошибки кэша
        }
    }

    /**
     * Построить ключ кэша для записи пользователя.
     *
     * @param string $id
     * @return string
     */
    private function buildUserCacheKey(string $id): string
    {
        return 'user:' . $id;
    }
}
