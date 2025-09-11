<?php // v0.4.2
/* framework/Faravel/Database/DatabaseAdmin.php
Purpose: Вспомогательные утилиты управления СУБД на этапе установки/сервиса (без контейнера).
FIX: Добавлены методы pingServer() и testReport() для диагностики (SafeMode/CLI).
     Обновлены PHPDoc и кросс-драйверная обработка.
*/

namespace Faravel\Database;

use PDO;
use PDOException;
use InvalidArgumentException;

/**
 * Lightweight DB admin helper for installer & tooling.
 *
 * This class avoids relying on the Application container and can connect
 * to the server without an existing database to perform CREATE/DROP
 * and other administrative checks.
 */
class DatabaseAdmin
{
    /**
     * Build DSN for server-level connection (no database in path).
     *
     * @param array{driver?:string,host?:string,port?:string|int,charset?:string} $cfg
     * @return string
     */
    protected static function buildServerDsn(array $cfg): string
    {
        $driver  = $cfg['driver'] ?? 'mysql';
        $host    = $cfg['host']   ?? '127.0.0.1';
        $port    = (string)($cfg['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
        $charset = $cfg['charset'] ?? 'utf8mb4';

        if ($driver === 'pgsql') {
            return "pgsql:host={$host};port={$port}";
        }
        // Default: mysql/mariadb
        return "mysql:host={$host};port={$port};charset={$charset}";
    }

    /**
     * Build DSN including database.
     *
     * @param array{driver?:string,host?:string,port?:string|int,database?:string,charset?:string} $cfg
     * @return string
     */
    protected static function buildDatabaseDsn(array $cfg): string
    {
        $driver  = $cfg['driver'] ?? 'mysql';
        $host    = $cfg['host']   ?? '127.0.0.1';
        $port    = (string)($cfg['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
        $db      = $cfg['database'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';

        if ($driver === 'pgsql') {
            return "pgsql:host={$host};port={$port};dbname={$db}";
        }
        return "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    }

    /**
     * Create PDO connection to server (no db).
     *
     * @param array{username?:string,password?:string} $cfg
     * @return PDO
     * @throws PDOException Если подключение не удалось.
     */
    protected static function connectServer(array $cfg): PDO
    {
        $dsn = self::buildServerDsn($cfg);
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    /**
     * Check if database exists.
     *
     * @param array{driver?:string,host?:string,port?:string|int,database:string,username?:string,password?:string} $cfg
     * @return bool
     * @throws InvalidArgumentException Если не задано имя БД.
     * @throws PDOException Если не удалось подключиться к серверу.
     */
    public static function databaseExists(array $cfg): bool
    {
        $driver = $cfg['driver'] ?? 'mysql';
        $dbName = $cfg['database'];
        if ($dbName === '') {
            throw new InvalidArgumentException('Empty database name.');
        }
        $pdo = self::connectServer($cfg);

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
            $stmt->execute(['name' => $dbName]);
            return (bool)$stmt->fetchColumn();
        }

        // mysql
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name');
        $stmt->execute(['name' => $dbName]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Create database if it doesn't exist.
     *
     * @param array{driver?:string,host?:string,port?:string|int,database:string,username?:string,password?:string,charset?:string,collation?:string} $cfg
     * @return void
     * @throws InvalidArgumentException Если не задано имя БД.
     * @throws PDOException Если не удалось выполнить команду.
     */
    public static function createDatabaseIfNotExists(array $cfg): void
    {
        $driver = $cfg['driver'] ?? 'mysql';
        $dbName = $cfg['database'];
        if ($dbName === '') {
            throw new InvalidArgumentException('Empty database name.');
        }
        $pdo = self::connectServer($cfg);

        if ($driver === 'pgsql') {
            if (!self::databaseExists($cfg)) {
                $pdo->exec('CREATE DATABASE "' . str_replace('"', '""', $dbName) . '"');
            }
            return;
        }

        // mysql
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';
        $sql = sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            str_replace('`','``',$dbName),
            $charset,
            $collation
        );
        $pdo->exec($sql);
    }

    /**
     * Drop database (dangerous).
     *
     * @param array{driver?:string,host?:string,port?:string|int,database:string,username?:string,password?:string} $cfg
     * @return void
     * @throws InvalidArgumentException Если не задано имя БД.
     * @throws PDOException Если не удалось выполнить команду.
     */
    public static function dropDatabase(array $cfg): void
    {
        $driver = $cfg['driver'] ?? 'mysql';
        $dbName = $cfg['database'];
        if ($dbName === '') {
            throw new InvalidArgumentException('Empty database name.');
        }
        $pdo = self::connectServer($cfg);

        if ($driver === 'pgsql') {
            $pdo->exec('DROP DATABASE IF EXISTS "' . str_replace('"', '""', $dbName) . '"');
            return;
        }

        $pdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`','``',$dbName) . '`');
    }

    /**
     * Attempt full connection to database.
     *
     * @param array{driver?:string,host?:string,port?:string|int,database:string,username?:string,password?:string,charset?:string} $cfg
     * @return bool
     */
    public static function canConnect(array $cfg): bool
    {
        try {
            $dsn = self::buildDatabaseDsn($cfg);
            $user = $cfg['username'] ?? '';
            $pass = $cfg['password'] ?? '';
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->query('SELECT 1')->fetchColumn();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Ping the DB server (server-level connect without selecting a database).
     * Returns the elapsed seconds (float). Throws on failure.
     *
     * Preconditions: host/port reachable with provided credentials (if required by server).
     * Side effects: opens and closes a short-lived PDO connection.
     *
     * @param array{driver?:string,host?:string,port?:string|int,username?:string,password?:string,charset?:string} $cfg
     * @param int $timeoutMs Soft timeout for measurement (best-effort, relies on driver socket timeouts).
     * @return float Elapsed time in seconds.
     * @throws PDOException On connection failure.
     * @example
     *  $elapsed = DatabaseAdmin::pingServer($cfg, 2000); // ~0.8 (seconds)
     */
    public static function pingServer(array $cfg, int $timeoutMs = 1000): float
    {
        $start = microtime(true);
        // Note: PDO has limited per-connection timeout control; we rely on driver defaults.
        // For MySQL, consider setting 'PDO::ATTR_TIMEOUT' if supported by the driver.
        $dsn = self::buildServerDsn($cfg);
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // @phpstan-ignore-next-line: not all drivers support ATTR_TIMEOUT
            PDO::ATTR_TIMEOUT => max(1, (int)ceil($timeoutMs / 1000)),
        ]);
        // Simple no-op query to ensure connection is alive
        if (($cfg['driver'] ?? 'mysql') === 'pgsql') {
            $pdo->query('SELECT 1')->fetchColumn();
        } else {
            $pdo->query('SELECT 1')->fetchColumn();
        }
        return microtime(true) - $start;
    }

    /**
     * Build a structured diagnostics report for connectivity.
     *
     * This method attempts:
     *  1) server-level connect (ping),
     *  2) database existence check (if database provided),
     *  3) full DB connection (dsn includes database).
     *
     * @param array{driver?:string,host?:string,port?:string|int,database?:string,username?:string,password?:string,charset?:string,collation?:string} $cfg
     * @return array{server_ok:bool,elapsed:float,db_exists:?bool,connect_ok:?bool,error:?string}
     * @example
     *  $rep = DatabaseAdmin::testReport($cfg);
     *  if (!$rep['server_ok']) { /* show guidance *\/ }
     */
    public static function testReport(array $cfg): array
    {
        $rep = [
            'server_ok' => false,
            'elapsed'   => 0.0,
            'db_exists' => null,
            'connect_ok'=> null,
            'error'     => null,
        ];

        try {
            $rep['elapsed']   = self::pingServer($cfg, 2000);
            $rep['server_ok'] = true;
        } catch (PDOException $e) {
            $rep['error'] = 'Server connect failed: ' . $e->getMessage();
            return $rep;
        }

        // If database is specified — check existence and try full connect
        $db = (string)($cfg['database'] ?? '');
        if ($db !== '') {
            try {
                $rep['db_exists'] = self::databaseExists($cfg);
            } catch (PDOException $e) {
                $rep['db_exists'] = null;
                $rep['error'] = 'Exists check failed: ' . $e->getMessage();
            } catch (InvalidArgumentException $e) {
                $rep['db_exists'] = null;
            }
            try {
                $rep['connect_ok'] = self::canConnect($cfg);
            } catch (\Throwable $e) {
                $rep['connect_ok'] = false;
                $rep['error'] = 'Full connect failed: ' . $e->getMessage();
            }
        }

        return $rep;
    }
}
