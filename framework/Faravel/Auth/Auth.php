<?php

namespace Faravel\Auth;

use Faravel\Http\Session;
use Faravel\Support\Facades\DB;

/**
 * Простая служба аутентификации.
 * Поддерживает два режима хранения в сессии:
 *  - новый: весь массив пользователя под ключом 'user';
 *  - legacy-совместимость: также кладём 'user_id'.
 * Умеет подтянуть пользователя из БД по user_id, если в сессии нет 'user'.
 */
class Auth
{
    protected Session $session;

    /** @var array<int,array<string,mixed>> */
    protected array $users = [];

    protected string $storagePath;

    public function __construct()
    {
        $this->session = new Session();
        $this->session->start();

        $this->storagePath = \base_path('storage/users.json');
        $this->loadUsers();
    }

    /**
     * Загрузить пользователей из JSON-хранилища (legacy).
     */
    protected function loadUsers(): void
    {
        if (is_file($this->storagePath)) {
            $json = file_get_contents($this->storagePath);
            $data = json_decode($json, true);
            if (is_array($data)) {
                $this->users = $data;
            }
        }
    }

    /**
     * Сохранить пользователей в JSON-хранилище (legacy).
     */
    protected function saveUsers(): bool
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        $bytes = file_put_contents(
            $this->storagePath,
            json_encode($this->users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if ($bytes === false) {
            throw new \RuntimeException("Unable to save users file.");
        }
        return true;
    }

    /**
     * Попытка входа по простому JSON-хранилищу (legacy).
     *
     * @param array<string,string> $credentials (username, password)
     */
    public function attempt(array $credentials): bool
    {
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        foreach ($this->users as $user) {
            if (
                isset($user['username'], $user['password']) &&
                $user['username'] === $username &&
                $user['password'] === $password
            ) {
                $this->putIntoSession($user);
                return true;
            }
        }
        return false;
    }

    /**
     * Регистрация в JSON-хранилище (legacy).
     *
     * @param array<string,string> $data (username, password)
     */
    public function register(array $data): bool
    {
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        if (!$username || !$password) {
            return false;
        }

        foreach ($this->users as $user) {
            if (($user['username'] ?? null) === $username) {
                return false;
            }
        }

        $id = !empty($this->users)
            ? (max(array_column($this->users, 'id')) + 1)
            : 1;

        $new = [
            'id'       => $id,
            'username' => $username,
            'password' => $password, // legacy — без хеша
        ];

        $this->users[] = $new;
        $this->saveUsers();

        $this->putIntoSession($new);
        return true;
    }

    /**
     * Принудительная авторизация уже найденного пользователя (массив из БД, обязателен 'id').
     *
     * @param array<string,mixed> $user
     */
    public function login(array $user): void
    {
        if (!isset($user['id'])) {
            throw new \InvalidArgumentException('Auth::login expects array with key "id"');
        }
        $this->putIntoSession($user);
    }

    /**
     * Актуальный пользователь или null.
     *
     * @return array<string,mixed>|null
     */
    public function user(): ?array
    {
        // 1) если уже есть полный пользователь в сессии — возвращаем его
        $inSession = $this->session->get('user');
        if (is_array($inSession)) {
            return $inSession;
        }

        // 2) иначе пробуем по user_id достать из БД
        $id = $this->session->get('user_id');
        if ($id) {
            $user = DB::table('users')->where('id', '=', $id)->first();
            if ($user) {
                // кэшируем на время текущей сессии
                $this->session->put('user', $user);
                return $user;
            }
            // fallback на legacy JSON (вдруг пользователь из файла)
            foreach ($this->users as $u) {
                if (($u['id'] ?? null) === $id) {
                    $this->session->put('user', $u);
                    return $u;
                }
            }
        }

        return null;
    }

    public function id(): ?string
    {
        $u = $this->user();
        return $u['id'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function logout(): void
    {
        $this->session->forget('user');
        $this->session->forget('user_id');
    }

    /**
     * Положить в сессию и полный user, и user_id для полной совместимости.
     *
     * @param array<string,mixed> $user
     */
    protected function putIntoSession(array $user): void
    {
        $this->session->put('user', $user);
        if (isset($user['id'])) {
            $this->session->put('user_id', $user['id']);
        }
    }
}
