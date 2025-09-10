<?php

namespace Faravel\Cache;

/**
 * Кеш, основанный на расширении Memcached. Позволяет хранить
 * данные в общедоступном memcached‑сервере. Перед использованием
 * убедитесь, что PHP расширение memcached доступно в вашей
 * среде выполнения. Если расширение не установлено, будет
 * выброшено исключение при создании объекта.
 */
class MemcachedCache
{
    /**
     * Экземпляр Memcached
     *
     * @var \Memcached
     */
    protected \Memcached $memcached;

    /**
     * Создать новый MemcachedCache.
     *
     * @param array $config Конфигурация, содержащая массив servers. Каждый
     *                      элемент должен иметь ключи host, port и weight.
     *
     * @throws \RuntimeException
     */
    public function __construct(array $config)
    {
        if (!class_exists('Memcached')) {
            throw new \RuntimeException('Memcached extension is not installed');
        }
        $this->memcached = new \Memcached();
        if (isset($config['servers']) && is_array($config['servers'])) {
            $this->memcached->addServers($this->normalizeServers($config['servers']));
        } else {
            // Добавляем сервер по умолчанию
            $this->memcached->addServer('127.0.0.1', 11211);
        }
    }

    /**
     * Нормализовать массив серверов для Memcached::addServers.
     *
     * @param array $servers
     * @return array
     */
    protected function normalizeServers(array $servers): array
    {
        $result = [];
        foreach ($servers as $server) {
            $host = $server['host'] ?? '127.0.0.1';
            $port = $server['port'] ?? 11211;
            $weight = $server['weight'] ?? 0;
            $result[] = [$host, (int) $port, (int) $weight];
        }
        return $result;
    }

    /**
     * Сохранить значение в кеш.
     *
     * @param string $key
     * @param mixed $value
     * @param int $seconds Время жизни в секундах. 0 означает бесконечный срок.
     */
    public function put(string $key, mixed $value, int $seconds = 0): void
    {
        // Memcached считает 0 как бесконечный TTL
        $this->memcached->set($key, $value, $seconds);
    }

    /**
     * Получить значение из кеша.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($key);
        if ($value === false && $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
            return $default;
        }
        return $value;
    }

    /**
     * Удалить элемент из кеша.
     *
     * @param string $key
     */
    public function forget(string $key): void
    {
        $this->memcached->delete($key);
    }
}