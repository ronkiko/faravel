<?php

namespace App\Services;

use Faravel\Support\Config;
use Faravel\Support\Facades\Logger;

/**
 * Простая реализация менеджера синхронизации.
 * Пока выполняет только логирование запуска в разных режимах.
 */
class SyncManager
{
    protected array $config;

    public function __construct()
    {
        $this->config = Config::get('sync');
    }

    /**
     * Запустить синхронизацию в указанном режиме.
     *
     * @param string $mode inbound|outgoing|incoming
     */
    public function run(string $mode): void
    {
        $logger = Logger::getFacadeRoot();
        $logger->info('SyncManager: running mode ' . $mode);
        // Здесь может быть вызов соответствующих сервисов (PeerManager, EventHandler и т.д.)
        switch ($mode) {
            case 'inbound':
                // импорт входящих событий
                $logger->info('SyncManager: inbound sync stub executed');
                break;
            case 'outgoing':
                // отправка событий на другие узлы
                $logger->info('SyncManager: outgoing sync stub executed');
                break;
            case 'incoming':
                // получение от peers (в учебной реализации не поддерживается)
                $logger->info('SyncManager: incoming sync stub executed');
                break;
            default:
                $logger->warning('SyncManager: unknown mode ' . $mode);
        }
    }
}