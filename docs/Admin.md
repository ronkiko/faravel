
---

```md
# docs/Admin.md

# SafeMode Admin — единая аварийная админ-панель

Админка объединяет вспомогательные режимы (проверки БД, инсталлятор) под **одной дверью**: `/admin/`.
Доступ — по ключу из окружения, авторизация запоминается в сессии.

## Доступ и авторизация

- Точка входа: `public/admin/index.php`.
- Ключ проверяется **один раз** за сессию и сохраняется в `$_SESSION`.
- Переменные окружения:
  - `SERVICEMODE_KEY` — приоритетный ключ;
  - `ADMIN_KEY` — используется, если первый не задан.

> Если не задан **ни один** ключ — вход запрещён.

## Архитектура

- `public/admin/index.php` — единственная дверь: проверка ключа → роутинг модулей.
- Подключаемые модули начинаются с подчёркивания и защищены константой `ADMIN_ENTRY`:
  - `_service.php` — «Проверки БД»
  - `_install.php` — «Инсталлятор»
- Общий UI/хелперы: `_helpers.php` (сессии, layout, алерты).

### Сервисы

- Работа с БД централизована в `Faravel\Database\DatabaseAdmin` (статические методы):
  - `pingServer($cfg)`, `databaseExists($cfg)`, `createDatabaseIfNotExists($cfg)`,
    `dropDatabase($cfg)`, `canConnect($cfg)`, `testReport($cfg)`.
- Миграции/сиды: `framework/migrator.php` экспортирует `faravel_migrate_all()` и
  `faravel_seed_all()`; CLI-обёртка — `tools/migrate.php`.

## Модули

### Проверки БД (`page=service`)
- **Диагностика**: ping сервера, отчёт `testReport()`.
- **Операции**: exists, create, drop, connect (create/drop требуют подтверждения).
- Конфигурация подтягивается из ENV (`DB_*`) и редактируется в форме.

### Инсталлятор (`page=install`)
- connect/create/drop (через `DatabaseAdmin`).
- миграции/сиды (через `framework/migrator.php`): **Migrate**, **Seed**, **Fresh**.
- `public/installed.lock`:
  - **создание** — блокирует повторный запуск инсталлятора;
  - **удаление** — доступно только с явным подтверждением.

---

## Безопасность (обязательно к исполнению)

### Чек-лист

1. **Задайте ключ**: `SERVICEMODE_KEY` (рекомендуется) или `ADMIN_KEY` в `.env`/секретах.
2. **Ограничьте доступ к `/admin/`** на уровне веб-сервера:
   - BasicAuth **и/или** allow/deny по IP.
3. **Поставьте `public/installed.lock`** после развёртывания (кнопкой в инсталляторе).
4. **Права на файлы**:
   - `.env` — `640`, владелец — пользователь PHP;
   - `storage/` и `bootstrap/cache/` — на запись процессом PHP;
   - запретите листинг директорий.
5. **Никогда** не коммитьте ключи/пароли в git.
6. **Ротация ключа**: смените `SERVICEMODE_KEY`, завершите сессию (кнопка «Выйти»).

### Примеры конфигурации

**Nginx**
location /admin/ {
try_files $uri $uri/ /admin/index.php?$query_string;

# Рекомендуется включить хотя бы один из вариантов ниже:
# auth_basic "Restricted";
# auth_basic_user_file /etc/nginx/.htpasswd;

# Или фильтрация по IP (пример):
# allow 192.168.0.0/16;
# allow 127.0.0.1;
# deny all;


}


**Apache (пример)**


<Directory "/var/www/project/public/admin">
AllowOverride All
Require ip 127.0.0.1 192.168.0.0/16
# Или BasicAuth:
# AuthType Basic
# AuthName "Restricted"
# AuthUserFile /etc/apache2/.htpasswd
# Require valid-user
</Directory>


---

## CLI-миграции



php tools/migrate.php --migrate [--json]
php tools/migrate.php --seed [--json]
php tools/migrate.php --fresh [--json]


Флаг `--json` глушит «болтливый» вывод и печатает структурированный JSON (удобно для CI).  
Код возврата: `0` — успех, `1` — ошибка.

---

## Траблшутинг

- «Доступ запрещён»: проверьте `SERVICEMODE_KEY/ADMIN_KEY` и ограничения веб-сервера.
- «Миграционный раннер не найден»: убедитесь, что `framework/migrator.php` на месте.
- Проблемы с БД: используйте «Диагностический отчёт» и проверьте `DB_*` в ENV.