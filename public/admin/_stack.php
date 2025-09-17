<?php // v0.4.117
/* public/admin/_stack.php
Purpose: Модуль «Проверка стека» для SafeMode‑админки. Выполняет
         статический анализ исходного кода, сравнивая объявленные в шапке
         каждого PHP‑файла контракты с фактическими публичными методами классов.
         Помогает выявить отступления от архитектуры и забытые обновления
         контрактов. Результаты выводятся в виде таблицы и отдельных секций.
FIX: Новый модуль. Использует службу App\Services\Admin\ContractChecker для
     обхода каталогов `app` и `framework`. Отличия выделяются цветом.
*/

declare(strict_types=1);

if (!defined('ADMIN_ENTRY')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

use App\Services\Admin\ContractChecker;

// Определяем базовый путь проекта (forum/). На один уровень вверх от /public/admin.
$projectRoot = realpath(__DIR__ . '/../../');
$scanDirs    = [$projectRoot . '/app', $projectRoot . '/framework'];

// Получаем результаты проверки контрактов (file => ['missing'=>[], 'extra'=>[]]).
$results = ContractChecker::check($scanDirs);

echo '<div class="panel"><div class="hd"><strong>Проверка стека</strong></div><div class="bd">';

// Скрываем описание теста в сворачиваемом блоке
echo '<details style="margin-bottom:12px"><summary style="cursor:pointer;font-weight:bold">Описание</summary>';
echo '<p class="muted">Этот тест сравнивает список публичных методов, описанный в разделе
<code>Contract:</code> каждого файла, с фактическими методами в классе. Несоответствие
указывает на необходимость обновить контракт или сигнатуры методов.</p>';
echo '</details>';

// Собираем дерево по пути файла.  Файлы без несоответствий также включаем,
// чтобы показать полную картину.
$tree = [];
foreach ($results as $file => $info) {
    $rel  = str_replace($projectRoot . '/', '', $file);
    $parts = explode('/', $rel);
    $ref  =& $tree;
    foreach ($parts as $idx => $part) {
        if ($idx === count($parts) - 1) {
            $ref[$part] = $info;
        } else {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref =& $ref[$part];
        }
    }
}

// Подсчитать кол-во файлов и количество проблемных файлов для директорий
/**
 * @param array<string,mixed> $node
 * @return array{total:int,issues:int}
 */
function stack_count_dir(array $node): array
{
    $total = 0;
    $issues = 0;
    foreach ($node as $key => $value) {
        if (isset($value['missing']) || isset($value['extra'])) {
            $total++;
            if (!empty($value['missing']) || !empty($value['extra'])) {
                $issues++;
            }
        } elseif (is_array($value)) {
            $child = stack_count_dir($value);
            $total += $child['total'];
            $issues += $child['issues'];
        }
    }
    return ['total' => $total, 'issues' => $issues];
}

// Рендер дерева директорий и файлов
/**
 * @param array<string,mixed> $node
 * @param string $prefix
 * @return void
 */
function stack_render_tree(array $node, string $prefix = ''): void
{
    ksort($node);
    foreach ($node as $name => $value) {
        if (isset($value['missing']) || isset($value['extra'])) {
            // файл с информацией о контракте
            $issues = (!empty($value['missing']) || !empty($value['extra']));
            $color = $issues ? 'red' : 'green';
            echo '<details><summary><span style="color:' . $color . '">📄 ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></summary>';
            if ($issues) {
                $missingList = empty($value['missing']) ? '—' : htmlspecialchars(implode(', ', $value['missing']), ENT_QUOTES, 'UTF-8');
                $extraList   = empty($value['extra']) ? '—' : htmlspecialchars(implode(', ', $value['extra']), ENT_QUOTES, 'UTF-8');
                echo '<div style="margin-left:12px">Недостающие методы: <span style="color:red">' . $missingList . '</span></div>';
                echo '<div style="margin-left:12px">Лишние методы: <span style="color:red">' . $extraList . '</span></div>';
            } else {
                echo '<div style="margin-left:12px;color:green">Контракт соответствует фактическим методам.</div>';
            }
            echo '</details>';
        } else {
            // директория
            $counts = stack_count_dir($value);
            $label = '📁 ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' <span style="font-size:11px;color:#666">(' . $counts['issues'] . '/' . $counts['total'] . ')</span>';
            echo '<details><summary>' . $label . '</summary>';
            stack_render_tree($value, $prefix === '' ? $name : $prefix . '/' . $name);
            echo '</details>';
        }
    }
}

// Вывод дерева
echo '<div style="border:1px solid #ddd;border-radius:6px;padding:8px;max-height:420px;overflow:auto">';
stack_render_tree($tree);
echo '</div>';

echo '</div></div>';