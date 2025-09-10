<?php

require_once __DIR__ . '/PeerManager.php';
require_once __DIR__ . '/EventHandler.php';
require_once __DIR__ . '/CryptoHelper.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/FileWriter.php';

class SyncManager
{
    private string $mode;
    private PeerManager $peerManager;
    private EventHandler $eventHandler;
    private CryptoHelper $crypto;
    private FileWriter $fileWriter;
    private Logger $logger;
    private bool $debug;

    public function __construct(string $mode = 'incoming')
    {
        $this->mode = $mode;

        $this->logger = new Logger();
        $this->crypto = new CryptoHelper($this->logger);
        $this->fileWriter = new FileWriter($this->logger);
        $this->peerManager = new PeerManager($this->logger, $this->crypto, $this->fileWriter);
        $this->eventHandler = new EventHandler($this->logger, $this->fileWriter);

        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
    }

    public function run(): void
    {
        if ($this->debug) {
            $this->logger->debug("SYNC MANAGER: launched with mode '{$this->mode}'");
        }

        switch ($this->mode) {
            case 'incoming':
                if ($this->debug) {
                    $this->logger->debug("SYNC MANAGER: routing to PeerManager (incoming)");
                }
                $this->handleIncoming();
                break;
            case 'outgoing':
                if ($this->debug) {
                    $this->logger->debug("SYNC MANAGER: routing to PeerManager (outgoing)");
                }
                $this->handleOutgoing();
                break;
            case 'inbound':
                if ($this->debug) {
                    $this->logger->debug("SYNC MANAGER: routing to EventHandler (inbound)");
                }
                $this->handleInbound();
                break;
            default:
                $this->logger->log("Unknown sync mode: {$this->mode}", true);
        }
    }

    private function handleIncoming(): void
    {
        if (php_sapi_name() === 'cli') {
            #            fwrite(STDERR, "[ERROR] Incoming sync must be triggered via HTTP request (GET or POST).\n");exit(1);
            die("[ERROR] Incoming sync must be triggered via HTTP request (GET or POST).\n");
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->peerManager->authorizePeer();
        } elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->peerManager->serverSync();
        } else {
            echo "[ERROR] Unsupported request method.\n";
        }
    }

    private function handleOutgoing(): void
    {
        $dayTo = (int) floor(time() / 86400);
        $dayFrom = $dayTo - (SYNC_DAYS_BACK - 1);

        if (($dayTo - $dayFrom + 1) > 100) {
            $this->logger->log("[WARN] Sync aborted: date range exceeds 100 days (from $dayFrom to $dayTo)", true);
            return;
        }

        $this->peerManager->clientSync($dayFrom, $dayTo, SYNC_EVENT_TYPES);
    }


    private function handleInbound(): void
    {
        $this->eventHandler->importInboundEvents();
    }
}
