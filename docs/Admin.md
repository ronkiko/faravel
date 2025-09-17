
---

```md
# docs/Admin.md

# Админ-панель Faravel и SafeMode

Начиная с версии **v0.4.118** в Faravel существует две админских зоны:

1. **MVC‑админка** — полноценная панель управления форумом по адресу `/admin`.  Она
   работает поверх основного ядра Faravel, использует контроллеры и
   middleware и доступна только администраторам (role_id ≥ 6).  Через неё
   можно управлять настройками, категориями и форумами.
2. **SafeMode** — аварийно‑сервисный режим, перенесённый на `/safemode/`.  Он
   предназначен для диагностики БД, установки приложения и проверки
   целостности кода.  SafeMode независим от ядра и загружает только
   минимальный бутстрап.

Этот документ описывает первую область: MVC‑админку.

## Доступ и авторизация

- **Точка входа**: `/admin`.  За отображение админки отвечает
  `AdminController@index`.
- **Роль**: доступ получают только пользователи с `role_id ≥ 6` (администраторы,
  разработчики и владельцы).  Middleware `AdminOnly` проверяет роль
  перед выполнением действия.
- **Авторизация**: пользователь должен быть залогинен.  Если гость
  обращается к `/admin`, он будет перенаправлён на `/login`.

## Архитектура

- **Точка входа**: `/admin` соответствует методу `index()` класса
  `App\Http\Controllers\AdminController`.  Файл
  `public/admin/index.php` остаётся только для SafeMode (см. `docs/ServiceMode.md`).
- **Роутинг**: маршруты админки определены в `routes/web.php`.  Они
  охватывают панель (`/admin`), настройки (`/admin/settings`), категории
  (`/admin/categories`), форумы (`/admin/forums` и `/admin/forums/new`) и
  используют middleware `AuthMiddleware`, `VerifyCsrfToken` и `AdminOnly`.
- **Контроллеры**: `AdminController` рендерит панель и настройки,
  `AdminCategoryController` управляет категориями, `AdminForumController` —
  форумами.  Каждый контроллер строит `LayoutVM` через `LayoutService`.
- **Вьюхи**: Blade‑шаблоны админки находятся в `resources/views/admin/`.

### Сервисы

- Работа с БД централизована в `Faravel\Database\DatabaseAdmin` (статические методы):
  - `pingServer($cfg)`, `databaseExists($cfg)`, `createDatabaseIfNotExists($cfg)`,
    `dropDatabase($cfg)`, `canConnect($cfg)`, `testReport($cfg)`.
- Миграции/сиды: `framework/migrator.php` экспортирует `faravel_migrate_all()` и
  `faravel_seed_all()`; CLI-обёртка — `tools/migrate.php`.

## Возможности

MVC‑админка покрывает основные задачи управления форумом.  На текущий момент
реализованы следующие разделы:

- **Панель** (`/admin`) — стартовая страница админки.  Здесь отображается
  список модулей и ссылки на категории и форумы.
- **Настройки** (`/admin/settings`) — позволяет изменять конфигурацию
  форума.  В перспективе здесь появятся настройки тем оформления, почты и
  прочих параметров.
- **Категории** (`/admin/categories`) — CRUD‑интерфейс для категорий.
- **Форумы** (`/admin/forums`) и **Создание форума** (`/admin/forums/new`) —
  управление форумами внутри категорий.  Раздел использует те же принципы,
  что и управление категориями.

Отдельно следует упомянуть сервисные инструменты (проверка БД, инсталлятор,
проверка стека, контроль файлов).  Они больше не находятся в MVC‑админке;
за ними обращайтесь к документу [`docs/ServiceMode.md`](ServiceMode.md).

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