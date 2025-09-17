<?php // v0.4.117
/* public/admin/_checksum.php
Purpose: Модуль «Контроль файлов» для SafeMode‑админки. Позволяет
         администратору вычислять контрольные суммы всех файлов проекта,
         сохранять их в зашифрованном виде и сравнивать текущее состояние
         файловой системы с ранее сохранённым. Отображает древовидную
         структуру с цветовым выделением изменений и статистикой.
FIX: Новый модуль. Использует App\Services\Admin\ChecksumService для
     генерации дерева, вычисления различий и шифрования. Данные
     сохраняются в storage/checksums.enc.
*/

declare(strict_types=1);

if (!defined('ADMIN_ENTRY')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

use App\Services\Admin\ChecksumService;

// Вычисляем корень проекта: на два уровня вверх от /public/admin.
$projectRoot = realpath(__DIR__ . '/../../');
$storageDir  = $projectRoot . '/storage';
$dataFile    = $storageDir . '/checksums.enc';

// Получаем текущий admin key для шифрования/дешифрования. Без ключа —
// отключаем сохранение/загрузку (логин и работа модулей невозможны).
$adminKey = admin_resolve_key();

// Обработка отправки формы «Вычислить и сохранить». Только POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    // Пересчитываем дерево и сохраняем его, шифруя ключом.
    $tree  = ChecksumService::buildTree($projectRoot);
    $json  = json_encode($tree, JSON_UNESCAPED_UNICODE);
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    if ($adminKey !== '') {
        $enc = ChecksumService::encrypt($json, $adminKey);
        file_put_contents($dataFile, $enc);
        $saveMsg = 'Чексуммы сохранены.';
    } else {
        $saveMsg = 'Не удалось сохранить: ключ админки не задан.';
    }
}

// Считываем текущие и сохранённые хэши.
$currentTree = ChecksumService::buildTree($projectRoot);
$savedTree   = [];
if (is_file($dataFile) && $adminKey !== '') {
    $enc = @file_get_contents($dataFile);
    if ($enc !== false) {
        $json = ChecksumService::decrypt($enc, $adminKey);
        if ($json !== null) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $savedTree = $decoded;
            }
        }
    }
}

// Вычисляем различия.
$diffs = ChecksumService::diffTrees($currentTree, $savedTree);

// Счётчики изменений
$countChanged = 0;
$countNew     = 0;
$countRemoved = 0;
foreach ($diffs as $status) {
    if ($status === 'changed') { $countChanged++; }
    elseif ($status === 'new') { $countNew++; }
    elseif ($status === 'removed') { $countRemoved++; }
}

echo '<div class="panel"><div class="hd"><strong>Контроль файлов</strong></div><div class="bd">';
// Скрываем описание и кнопку в сворачиваемом блоке
echo '<details style="margin-bottom:12px"><summary style="cursor:pointer;font-weight:bold">Описание</summary>';
echo '<p class="muted">Этот модуль считает хэши всех файлов проекта (SHA‑256) и
сравнивает их с ранее сохранённым состоянием. Это помогает выявить
неожиданные изменения. Для сохранения и расшифровки используется ключ
админки.</p>';
echo '<form method="post" style="margin-bottom:12px"><input type="hidden" name="action" value="save">';
echo '<button type="submit">Пересчитать и сохранить хэши</button>';
echo '</form>';
echo '</details>';

if (isset($saveMsg)) {
    admin_alert('info', $saveMsg);
}

// Вывод сводной статистики
echo '<p>Всего файлов: ' . count($diffs) . '; изменённых: <span style="color:red">' . $countChanged
    . '</span>; новых: <span style="color:blue">' . $countNew
    . '</span>; удалённых: <span style="color:orange">' . $countRemoved . '</span>.</p>';

// Функция для рендера дерева
/**
 * @param array<string,mixed> $tree
 * @param array<string,string> $diffs
 * @param string $prefix
 * @return void
 */
function checksum_render_tree(array $tree, array $diffs, string $prefix = ''): void
{
    // Сортировать для стабильности
    ksort($tree);
    foreach ($tree as $name => $value) {
        $path = $prefix === '' ? $name : $prefix . '/' . $name;
        $status = $diffs[$path] ?? 'same';
        if (is_array($value)) {
            // Directory: подсчитаем количество файлов и изменённых файлов
            $counts = checksum_count_dir($diffs, $path);
            $label = '📁 ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') .
                ' <span style="font-size:11px;color:#666">(' . $counts['changed'] . '/' . $counts['total'] . ')</span>';
            echo '<details><summary>' . $label . '</summary>';
            checksum_render_tree($value, $diffs, $path);
            echo '</details>';
        } else {
            $color = 'green';
            if ($status === 'changed') { $color = 'red'; }
            elseif ($status === 'new') { $color = 'blue'; }
            elseif ($status === 'removed') { $color = 'orange'; }
            echo '<div><span style="color:' . $color . '">📄 ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></div>';
        }
    }
}

/**
 * Подсчитать количество файлов в директории и количество изменённых/новых/удалённых.
 *
 * @param array<string,string> $diffs
 * @param string $prefix
 * @return array<string,int>{changed:int,total:int}
 */
function checksum_count_dir(array $diffs, string $prefix): array
{
    $changed = 0;
    $total   = 0;
    $prefixWithSlash = $prefix . '/';
    foreach ($diffs as $path => $status) {
        if ($path === $prefix || strpos($path, $prefixWithSlash) === 0) {
            if ($status === 'changed' || $status === 'new' || $status === 'removed') {
                $changed++;
            }
            $total++;
        }
    }
    return ['changed' => $changed, 'total' => $total];
}

// Вывод дерева
echo '<div style="border:1px solid #ddd;border-radius:6px;padding:8px;max-height:420px;overflow:auto">';
checksum_render_tree($currentTree, $diffs);
echo '</div>';

echo '</div></div>';