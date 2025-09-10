<?php // step3.php
if (!defined('INSTALL_ENTRY')) {
    http_response_code(403);
    exit('Access denied.');
}

function handleStep3() {
    echo '<h2>🎉 Установка завершена!</h2>';
    echo '<p class="success">✅ Все шаги выполнены успешно. Ваша система готова к использованию.</p>';
    echo '<p>Теперь вы можете перейти на <a href="../">главную страницу сайта</a> или <a href="../admin/">в админку</a>.</p>';

    // Попытка удалить каталог install
    $installDir = __DIR__;
    $deleted = deleteDirectory($installDir);

    if ($deleted) {
        echo '<p class="success">🗑️ Каталог <code>install/</code> был автоматически удалён.</p>';
    } else {
        echo '<p class="warning">⚠️ Не удалось автоматически удалить каталог <code>install/</code>. Пожалуйста, удалите его вручную.</p>';
    }
}

// Рекурсивное удаление директории
function deleteDirectory($dir) {
    if (!is_dir($dir)) return false;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            if (!deleteDirectory($path)) return false;
        } else {
            if (!unlink($path)) return false;
        }
    }
    return rmdir($dir);
}
