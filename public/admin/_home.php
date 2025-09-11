<?php // v0.4.1
/* public/admin/_home.php
Назначение: демо-модуль «Панель» для SafeMode-админки (пример защищённого include).
FIX: начальная версия: защита ADMIN_ENTRY, карточка статуса и быстрые ссылки.
*/

declare(strict_types=1);

if (!defined('ADMIN_ENTRY')) {
    // Direct access protection.
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

/**
 * Отрисовывает простую панель состояния SafeMode-админки.
 *
 * Зачем: модуль-образец с проверкой ADMIN_ENTRY и базовым выводом.
 *
 * @return void
 */
function admin_home_panel(): void
{
    echo '<div class="panel"><div class="hd"><strong>Добро пожаловать</strong></div><div class="bd">';
    echo '<p>Вы вошли в SafeMode-админку. Выберите модуль слева.</p>';
    echo '<ul>';
    echo '<li>«Проверки БД» — диагностика и операции с БД (ping, exists, create…) — появится на шаге 2.</li>';
    echo '<li>«Инсталлятор» — запуск миграций/сидов с конфигом БД — появится на шаге 3.</li>';
    echo '</ul>';
    echo '</div></div>';

    echo '<div class="panel"><div class="hd"><strong>Быстрые ссылки</strong></div><div class="bd">';
    echo '<p><a href="index.php?page=service">Перейти к проверкам БД</a></p>';
    echo '<p><a href="index.php?page=install">Перейти к инсталлятору</a></p>';
    echo '</div></div>';
}

admin_home_panel();
