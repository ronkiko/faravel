# Overview: Abilities & Perks (what files are involved)

Короткая справка по двум сущностям авторизации и «плюшек». Без глубокой техники — только какие файлы это затрагивает.

---

## Abilities (права по ролям)

**Назначение:** фундаментальные права доступа (модерация, админка, системные действия).

### Где лежит логика

* `app/Services/Auth/AbilityService.php` — проверка прав, кэш справочника abilities.
* `app/Providers/AbilityServiceProvider.php` — регистрирует все abilities в Gate (используется в вьюхах и контроллерах).

  * Подключается один раз в `config/app.php` (секция `providers`).

### Админка (CRUD)

* `app/Http/Controllers/AdminAbilityController.php` — список/создание/редактирование/удаление.
* `resources/views/admin/abilities/index.blade.php` — таблица со списком, боковая навигация, липкие заголовки.
* `resources/views/admin/abilities/form.blade.php` — форма создания/правки.

### Данные

* `database/seeders/AbilitiesSeeder.php` — наполняет базовые права.
* Таблица: `abilities` (колонки: `id, name, label, description, min_role, created_at, updated_at`).

### Маршруты

* `routes/web.php` — раздел `/admin/abilities/*` (index/new/store/edit/update/delete).

---

## Perks (плюшки по группам)

**Назначение:** косметические/социальные возможности (например, подпись в профиле с определённой группы).

### Где лежит логика

* `app/Services/Auth/PerkService.php` — проверка наличия перка у пользователя, кэш справочника perks.
  *(Провайдера нет и не нужен; Gate в шаблонах не используем.)*

### Админка (CRUD)

* `app/Http/Controllers/AdminPerkController.php` — список/создание/редактирование/удаление (проверки прав внутри контроллера).
* `resources/views/admin/perks/index.blade.php` — список перков.
* `resources/views/admin/perks/form.blade.php` — форма. (Поле **Description** растянуто по ширине.)

### Данные

* Таблица: `perks` (колонки: `id, key, label, description, min_group_id, created_at, updated_at`).
* (Опционально) `database/seeders/PerksSeeder.php` — базовые перки, например:

  * `perk.profile.signature.use` (доступен с `min_group_id = 2`).

### Маршруты

* `routes/web.php` — раздел `/admin/perks/*` (index/new/store/edit/update/delete).

---

## Общее для обоих разделов

### UI/стили

* `public/style/ui-box.css` — нейтральный админ-кит (карточки, таблицы, кнопки, алерты, липкие хэдэры, сайдбар).
* `resources/views/layouts/theme.blade.php` — базовый шаблон, в который страницы «подпушивают» `ui-box.css`.

### Сообщения/UX

* В обоих разделах используется вывод флеш-сообщений:

  * `alert success` / `alert error` вверху списка/форм.

---

## Мини-итог

* **Abilities** — фундаментальные права, используются и через Gate, и напрямую через сервис.
* **Perks** — «плюшки» по группе, проверяются только через `PerkService` (без Gate-провайдера).
* Оба раздела имеют собственные контроллеры и вьюхи в `admin/*`, используют общий `ui-box.css`.
