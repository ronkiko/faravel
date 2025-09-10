<!-- tests/README.md v0.1.0
Назначение: как запускать встроенный раннер тестов.
FIX: первый релиз. -->
Запуск всех тестов:
php tools/test/run.php

Запуск с мутацией БД (создание темы) — Понимайте риск:
TEST_WRITE_DB=1 php tools/test/run.php

Что проверяется:
- VM: HubPageVM, CategoryPageVM, CreateTopicPageVM.
- Сервисы: HubQueryService, CategoryQueryService.  
- TopicCreateService — **SKIP по умолчанию**.
- Политика TopicPolicy — smoke-проверка (если класс присутствует).
