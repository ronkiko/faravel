# README.md

# Faravel

Учебный микрофреймворк в духе Laravel с жёстким MVC, «немым» Blade и без JS во вьюхах.

## Что нового в v0.4.6

- **SafeMode Admin**: единая админ-панель `/admin/` (единая точка входа `public/admin/index.php`):
  - проверка ключа 1 раз за сессию (используется `SERVICEMODE_KEY`, при отсутствии — `ADMIN_KEY`);
  - модули «Проверки БД» и «Инсталлятор» подключаются в общем каркасе (константа `ADMIN_ENTRY`);
  - подключаемые файлы начинаются с `_` и **недоступны** напрямую.
- **Инсталлятор**:
  - операции `connect/create/drop` на базе `Faravel\Database\DatabaseAdmin`;
  - миграции/сиды через **единый раннер** `framework/migrator.php`;
  - управление `public/installed.lock` (создание/удаление).
- **CLI-мигратор**: `php tools/migrate.php --migrate|--seed|--fresh [--json]`
  — чистый JSON-вывод для CI/Docker.
- Обновлена документация: `docs/Admin.md`, `SECURITY.md`, `CHANGELOG.md`.

См. подробности в [CHANGELOG.md](CHANGELOG.md).

## SafeMode Admin (аварийная панель)

Откройте /admin/, введите ключ (из SERVICEMODE_KEY либо ADMIN_KEY).

Выберите модуль:

Проверки БД — ping/exists/connect/create/drop/report

Инсталлятор — connect/create/drop/migrate/seed/fresh и installed.lock

Документация: docs/Admin.md

Безопасность: SECURITY.md


Миграции/Сиды из CLI
php tools/migrate.php --migrate
php tools/migrate.php --seed
php tools/migrate.php --fresh
# Для CI:
php tools/migrate.php --migrate --json

Структура

public/ — публичный корень (включая /admin/)

framework/ — ядро Faravel (migrator.php и пр.)

app/, routes/, resources/ — приложение

database/ — миграции и сиды

tools/ — CLI-утилиты (мигратор и т. п.)

docs/ — документация

## Лицензия

MIT. Подробнее см. LICENSE.