<?php # step1.php

if (!defined('INSTALL_ENTRY')) {
    http_response_code(403);
    exit('Прямой доступ запрещён');
}

function handleStep1()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    if (!isset($_SESSION['installer_config'])) {
        $_SESSION['installer_config'] = Spyc::YAMLLoad(CONFIG_PATH);
    }

    $config = &$_SESSION['installer_config'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // === 1. Обработка удаления peer (до загрузки новых значений)
        if (str_starts_with($action, 'remove_peer[')) {
            if (preg_match('/remove_peer\[(.+)\]/', $action, $m)) {
                $toRemove = $m[1];
                if (isset($config['sync']['SYNC_PEERS'][$toRemove])) {
                    unset($config['sync']['SYNC_PEERS'][$toRemove]);
                }
            }
        }

        // === 2. Обработка добавления нового peer
        if ($action === 'add_peer') {
            $alias = trim($_POST['new_peer_alias'] ?? '');
            $url   = trim($_POST['new_peer_url'] ?? '');
            $salt  = trim($_POST['new_peer_salt'] ?? '');

            $peers = $config['sync']['SYNC_PEERS'] ?? [];

            $isDuplicate = false;
            foreach ($peers as $existingAlias => $peer) {
                if (
                    $alias === $existingAlias ||
                    $peer['url'] === $url ||
                    $peer['salt'] === $salt
                ) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($alias !== '' && !$isDuplicate) {
                $config['sync']['SYNC_PEERS'][$alias] = ['url' => $url, 'salt' => $salt];
            }
        }

        // === 3. Обновление остальных полей конфигурации (включая sync, без SYNC_PEERS)
        $filtered = array_filter(
            $_POST,
            fn($k) => !in_array($k, ['action', 'new_peer_alias', 'new_peer_url', 'new_peer_salt']),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($filtered as $topKey => $section) {
            if (!is_array($section)) continue;

            foreach ($section as $key => $value) {
                if ($topKey === 'sync' && $key === 'SYNC_PEERS') {
                    // Обрабатываем SYNC_PEERS отдельно позже
                    continue;
                }

                $config[$topKey][$key] = is_array($value) ? $value : (string)$value;
            }
        }

        // === 4. Обновление SYNC_PEERS (только при сохранении)
        if ($action === 'save_config' && isset($_POST['sync']['SYNC_PEERS']) && is_array($_POST['sync']['SYNC_PEERS'])) {
            $config['sync']['SYNC_PEERS'] = []; // очищаем, чтобы не остались старые
            foreach ($_POST['sync']['SYNC_PEERS'] as $peerName => $peerData) {
                $url = trim((string)($peerData['url'] ?? ''));
                $salt = trim((string)($peerData['salt'] ?? ''));
                if ($url !== '' && $salt !== '') {
                    $config['sync']['SYNC_PEERS'][$peerName] = ['url' => $url, 'salt' => $salt];
                }
            }
        }

        // === 5. Сохранение в YAML-файл (только если нажата кнопка сохранения)
        if ($action === 'save_config') {
            try {
                $yaml = Spyc::YAMLDump($config, 2, 0);
                $result = @file_put_contents(CONFIG_PATH, $yaml);
                if ($result === false) {
                    throw new RuntimeException("Не удалось сохранить конфигурацию в файл: " . CONFIG_PATH);
                }

            } catch (Throwable $e) {
                die("Ошибка при сохранении конфигурации: " . htmlspecialchars($e->getMessage()));
            }
            redirect('step=2');
        }
    }

    // === 6. HTML-форма
    echo '<style>
    </style>';

    echo '<h2>Шаг 1: Настройка конфигурации</h2>';
    if (isset($_GET['saved'])) {
        echo '<p style="color: green;"><strong>Конфигурация успешно сохранена.</strong></p>';
    }

    echo '<form method="post" action="?step=1">';
    echo '<button type="submit" name="action" value="update" style="display:none"></button>';
    echo '<div class="fieldset-container">';

    foreach ($config as $topKey => $section) {
        echo '<fieldset>';
        echo '<legend>' . htmlspecialchars((string)$topKey) . '</legend>';
        echo '<table>';

        foreach ($section as $key => $value) {
            $fullKey = $topKey . '[' . $key . ']';

            if (is_array($value) && $key === 'SYNC_PEERS') {
                echo '<tr><td colspan="10"><strong>SYNC_PEERS</strong></td></tr>';

                foreach ($value as $peerName => $peerData) {
                    $safeName = htmlspecialchars((string)$peerName, ENT_QUOTES);
                    $url = htmlspecialchars((string)($peerData['url'] ?? ''), ENT_QUOTES);
                    $salt = htmlspecialchars((string)($peerData['salt'] ?? ''), ENT_QUOTES);

                    echo '<tr><td colspan="10">';
                    echo '<div class="peer-block">';
                    echo '<strong>' . $safeName . '</strong> ';
                    echo 'url: <input type="text" name="sync[SYNC_PEERS][' . $safeName . '][url]" value="' . $url . '" style="width:30%;display:inline-block;margin-right:0.5em;">';
                    echo 'salt: <input type="text" name="sync[SYNC_PEERS][' . $safeName . '][salt]" value="' . $salt . '" style="width:30%;display:inline-block;margin-right:0.5em;">';
                    echo '<button type="submit" name="action" value="remove_peer[' . $safeName . ']">Del</button>';
                    echo '</div></td></tr>';
                }

                echo '<tr>';
                echo '<td><strong>New peer</strong></td>';
                echo '<td><input type="text" name="new_peer_alias" placeholder="alias"></td>';
                echo '<td><input type="text" name="new_peer_url" placeholder="url"></td>';
                echo '<td><input type="text" name="new_peer_salt" placeholder="salt"></td>';
                echo '<td><button type="submit" name="action" value="add_peer">Add peer</button></td>';
                echo '</tr>';
            } elseif (!is_array($value)) {
                $safeVal = htmlspecialchars((string)$value, ENT_QUOTES);
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string)$key) . '</td>';
                echo '<td colspan="3"><input type="text" name="' . $fullKey . '" value="' . $safeVal . '"></td>';
                echo '</tr>';
            }
        }

        echo '</table>';
        echo '</fieldset>';
    }

    echo '</div>';
    echo '<button type="submit" name="action" value="save_config">Save and next</button>';
    echo '</form>';
}
