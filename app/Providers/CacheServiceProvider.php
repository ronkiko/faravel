<?php

namespace App\Providers;

use Faravel\Cache\Cache;
use Faravel\Foundation\ServiceProvider;

/**
 * Провайдер для файлового кеша. Регистрирует компонент cache в контейнере.
 */
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache', function () {
            // Получаем конфигурацию кеша. Если файл отсутствует, используем
            // файловый кеш по умолчанию.
            $config = \config('cache');
            $driver = $config['default'] ?? 'file';
            // Выбираем подходящий драйвер
            switch ($driver) {
                case 'memcached':
                    $storeConfig = $config['stores']['memcached'] ?? [];
                    return new \Faravel\Cache\MemcachedCache($storeConfig);
                case 'file':
                default:
                    $storeConfig = $config['stores']['file']['path'] ?? null;
                    return new Cache($storeConfig);
            }
        });
    }

    public function boot(): void
    {
        // nothing
    }
}