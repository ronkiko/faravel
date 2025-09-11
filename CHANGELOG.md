# CHANGELOG.md

# Changelog

Все заметные изменения этого проекта документируются в этом файле.

## [v0.4.6] — 2025-09-11
### Added
- SafeMode Admin (единая панель `/admin/`): ключ-аутентификация по сессии.
- Модуль «Инсталлятор»: создание/удаление `public/installed.lock`.
- CLI-мигратор `tools/migrate.php` с флагом `--json` (машинный вывод).
- Документация по безопасности: `SECURITY.md`, обновлён `docs/Admin.md`.

### Changed
- Интеграция с `Faravel\Database\DatabaseAdmin` через корректные статические методы
  (`pingServer`, `databaseExists`, `createDatabaseIfNotExists`, `dropDatabase`,
  `canConnect`, `testReport`).
- Инсталлятор и сервисные проверки упрощены и унифицированы под админ-панель.

## [v0.4.4] — 2025-09-10
### Added
- Первичная версия SafeMode (сервисные утилиты), авторизация по ключу.
- Базовый раннер миграций `framework/migrator.php`.

---

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/). Нумерация версий — SemVer.
