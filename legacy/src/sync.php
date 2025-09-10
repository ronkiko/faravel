<?php # sync.php

require_once dirname(__DIR__) . '/include/config.php';
require_once WWW_ROOT . '/include/config.php';
require_once WWW_ROOT . '/include/db.php';
require_once WWW_ROOT . '/include/functions.php';
require_once WWW_ROOT . '/class/SyncManager.php';

$mode = $argv[1] ?? ($_GET['mode'] ?? 'incoming');
$sync = new SyncManager($mode);

if (php_sapi_name() !== 'cli') {
    if ($mode === 'outgoing')
        header('Location: /admin/admin_sync.php#sync_started');
    elseif ($mode === 'inbound')
        header('Location: /admin/admin_sync.php#process_started');
}

$sync->run();
