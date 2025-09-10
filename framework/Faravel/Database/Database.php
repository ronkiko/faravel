<?php // v0.4.1
/*
framework/Faravel/Database/Database.php
Purpose: Менеджер БД (PDO) + обёртки select/scalar/insert/… и QueryBuilder. Поддерживает
         новый и старый формат конфигурации; делает устойчивый connect с ретраями.
FIX: Добавлены env-синонимы (driver/host/port/database/username/password), ретраи подключения
     (DB_RETRIES/DB_RETRY_MS) и улучшено сообщение об ошибке с host:port/db.
*/
namespace Faravel\Database;

use PDO;
use PDOException;
use Faravel\Support\Config;

/**
 * Менеджер базы данных Faravel.
 * Совместим с конфигами:
 *  - новый формат: driver/host/port/database/username/password/charset/collation
 *  - старый формат:           host/name/user/pass/password/charset
 */
class Database
{
    protected PDO $pdo;

    /**
     * @param array<string,mixed> $config Переопределение конфигурации (необязательно).
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config = (array) Config::get('database', []);
        }
        $this->pdo = $this->connect($config);
    }

    /**
     * Установить соединение с учётом ретраев.
     *
     * @param array<string,mixed> $cfg
     * @return \PDO
     * @throws \PDOException При окончательной неудаче подключения.
     */
    protected function connect(array $cfg): PDO
    {
        // Поддержка обоих форматов ключей + разумные дефолты
        $driver = (string) ($cfg['driver'] ?? 'mysql');
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);

        // имя БД: database | name
        $database = (string) ($cfg['database'] ?? $cfg['name'] ?? '');

        // логин: username | user
        $username = (string) ($cfg['username'] ?? $cfg['user'] ?? '');

        // пароль: password | pass
        $password = (string) ($cfg['password'] ?? $cfg['pass'] ?? '');

        // кодировка и колляция (для mysql)
        $charset = (string) ($cfg['charset'] ?? 'utf8mb4');
        $coll = (string) ($cfg['collation'] ?? 'utf8mb4_unicode_ci');

        // Политика ретраев (мс)
        $retries = (int) \max(1, (int) ($cfg['retries'] ?? 3));
        $sleepMs = (int) \max(0, (int) ($cfg['retry_sleep_ms'] ?? 250));

        // DSN
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ($database ?: ':memory:');
        } elseif ($driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        } else {
            // mysql по умолчанию
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $attempt = 0;
        $lastErr = null;

        while ($attempt < $retries) {
            try {
                return new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                $lastErr = $e;
                $attempt++;
                if ($attempt >= $retries) {
                    // Бросаем расширенное сообщение: куда пытались подключиться
                    $safeUser = $username !== '' ? $username : 'anonymous';
                    $connInfo = "{$driver}://{$safeUser}@{$host}:{$port}/{$database}";
                    throw new PDOException(
                        "[Faravel\\Database] DB connection failed after {$attempt} attempt(s) "
                        . "to {$connInfo}: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }
        }

        // Теоретически недостижимо
        throw $lastErr ?? new PDOException('[Faravel\\Database] Unknown DB connection error');
    }

    /**
     * Reconnect with new configuration.
     *
     * @param array<string,mixed> $cfg
     * @return void
     */
    public function reconnect(array $cfg): void
    {
        $this->pdo = $this->connect($cfg);
    }

    /** Выполнить произвольный SQL без выборки. */
    public function statement(string $sql, array $bindings = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    /** Выборка нескольких строк. */
    public function select(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /** Первое скалярное значение. null, если строк нет. */
    public function scalar(string $sql, array $bindings = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $v = $stmt->fetchColumn(0);
        return ($v === false) ? null : $v;
    }

    /** INSERT/UPDATE/DELETE — вернуть число затронутых строк. */
    public function insert(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /** INSERT и вернуть lastInsertId(). */
    public function insertGetId(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE — вернуть число затронутых строк. */
    public function update(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /** DELETE — вернуть число затронутых строк. */
    public function delete(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    // Транзакции
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }
    public function commit(): void
    {
        $this->pdo->commit();
    }
    public function rollBack(): void
    {
        if ($this->pdo->inTransaction())
            $this->pdo->rollBack();
    }

    /** Сырое PDO-подключение. */
    public function connection(): PDO
    {
        return $this->pdo;
    }

    /** Вернуть QueryBuilder для таблицы. */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this->pdo, $table);
    }
}
