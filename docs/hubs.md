<!-- docs/hubs.md v0.1.0
Архитектура Taggable Hubs: схемы таблиц, сценарии выборок, витрины топ-10, вход
в хаб и просмотр темы в контексте хаба. Метрики и статистика.
FIX: первичная структура документа. -->

# Taggable Hubs

## Таблицы
- `tags`, `taggables`, `category_tag`, `tag_stats`.

## Сценарии
- Витрина категории → топ-10 тегов из `tag_stats`.
- Хаб → список тем по `taggables(entity='topic')`.
- Тема в хабе → проверка привязки + пилюли темы (теги темы и постов).

## Контракты
- Расширения к TopicPageContract для контекста хаба.


```markdown
<!-- docs/hubs.md v0.2.0
Архитектура Taggable Hubs: схема, выборки, обновление статистики, инварианты.
FIX: добавлены паттерны индексов и обновления tag_stats. -->

# Taggable Hubs

## Таблицы

- `tags(id, slug UNIQUE, title, color?, is_active, created_by, ts...)`
- `taggables(tag_id, entity ENUM('topic','post'), entity_id, topic_id, created_at, PK(tag_id,entity,entity_id))`
- `category_tag(category_id, tag_id, position?)`
- `tag_stats(category_id, tag_id, topics_count, last_activity_at, ts...)`

Ключевые индексы:
- `idx_taggables_tag_topic(tag_id, topic_id)`
- `idx_taggables_entity(entity, entity_id)`
- `idx_taggables_entity_topic(entity, topic_id)`
- `idx_tag_stats_last(category_id, last_activity_at)`

## Выборки

- Витрина категории: топ-10 тегов из `tag_stats` по `last_activity_at` убыв.
- Хаб: темы по `taggables` с `entity='topic'` и сортировкой по `topics.last_post_at` убыв.
- Тема в хабе: проверка связи `ensureTopicHasTag(tag_id, topic_id)`.

## Обновление статистики

- При создании темы: `tag_stats.topics_count++`, `last_activity_at=now`.
- При ответе: `last_activity_at=now` для всех тегов темы.
- Только активные теги влияют на статистику.

## Инварианты

- Один и тот же тег не дублируется в `taggables` для одного `(entity, entity_id)`.
- Удаление поста не снижает `topics_count`, но может не менять `last_activity_at`.
- Цвета пилюль берём из `tags.color`, остальное из VM.

## Ошибки и UX

- Если в URL хаба тег не привязан к теме, редирект на канонический URL темы без хаба.
- `returnTo` всегда указывает текущую страницу, чтобы сохранить контекст.
