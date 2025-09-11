# SafeMode Admin — единая аварийная админ-панель

Админка объединяет вспомогательные режимы (проверки БД, инсталлятор и т. д.) под **одной дверью**:
`/admin/`. Доступ — по ключу из окружения, авторизация запоминается в сессии.

## Доступ и безопасность

- URL: `/admin/`
- Ключ проверяется один раз и хранится в сессии (флаг входа).
- Ключи: приоритетно используется `SERVICEMODE_KEY`, если не задан — берётся `ADMIN_KEY`.
- Рекомендуется ограничить `/admin/` на уровне веб-сервера (BasicAuth, allow/deny по IP).

Пример для Nginx:

location /admin/ {
try_files $uri $uri/ /admin/index.php?$query_string;
# auth_basic "Restricted"; # опционально
# auth_basic_user_file /etc/nginx/.htpasswd; # опционально
# allow 192.168.0.0/16; deny all; # опционально
}

## Архитектура и файлы

- `public/admin/index.php` — **единая точка входа** (проверка ключа → сессия → роутинг модулей).
- `public/admin/_helpers.php` — общие хелперы админки (сессия, layout, алерты, **фабрика DBA**).
- `public/admin/_home.php` — приветственная панель.
- `public/admin/_service.php` — модуль «Проверки БД».
- `public/admin/_install.php` — модуль «Инсталлятор».

Все **подключаемые модули** начинаются с подчёркивания и **защищены** от прямого доступа:
в начале файла они проверяют наличие константы `ADMIN_ENTRY`, которую объявляет только
`/admin/index.php`. Таким образом, модули доступны **только** через админку.

### Фабрика DatabaseAdmin

`_helpers.php` содержит `admin_make_database_admin($cfg)`, которая безопасно создаёт
`\Faravel\Database\DatabaseAdmin` (даже если у класса **нет конструктора**) и по возможности
передаёт конфигурацию через методы `configure|setConfig|withConfig`.

## Модули

### Проверки БД (`page=service`)
- Диагностика: `ping`, `canConnect`, отчёт `testReport()`
- Работа с БД: `exists`, `create`, `drop` (требуют явного подтверждения)
- Конфиг БД подхватывается из ENV (`DB_*`), можно править в форме

### Инсталлятор (`page=install`)
- Проверка подключения, `create/drop`.
- Миграции/Сиды через `framework/migrator.php`:
  - **Migrate**, **Seed**, и **Fresh** (drop+create+мiгр.+сиды — «Fresh» выполняется из админки).
- Управление `installed.lock`:
  - Создание файла (блокирует повторные установки)
  - Удаление файла (с отдельным подтверждением)

## CLI-режим (новое)

Для CI/Docker есть обёртка:

php tools/migrate.php --migrate [--json]
php tools/migrate.php --seed [--json]
php tools/migrate.php --fresh [--json]

- `--json` включает **машинный вывод** (JSON) и глушит «болтливый» stdout мигратора.
- Коды возврата: `0` — успех, `1` — ошибка.
- «Fresh» в CLI = migrate + seed (DROP/CREATE БД — через админку или ваш DBA-инструмент).

## Советы

- Держите ключи (`SERVICEMODE_KEY` / `ADMIN_KEY`) в `.env`/секретах и не коммитьте их.
- На проде прикрывайте `/admin/` базовой аутентификацией или по IP.
- `installed.lock` хранится в `public/`. Его наличие скрывает опасные операции в UI инсталлятора.