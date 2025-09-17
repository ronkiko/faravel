<!-- docs/forum-contracts.md v0.4.2
Purpose: Контракты данных для страниц форума: перечень общих типов, описания
         страниц хаба, категории, создания тем, постов и базовых полей.
FIX: Объединены дублирующиеся версии (v0.1.0 и v0.2.0) и приведён в
     актуальный вид; добавлено описание эволюции контрактов и инварианты.
-->

# Контракты данных

## Общие типы

```json
// UserLite
{"id":"<uuid>","username":"<string>","group_id":1,"role_id":6}

// AbilityMap
{"forum.post.create":true,"forum.post.edit.own":true,"forum.post.delete.own.soft":true}

// TagPill
{"slug":"linux","title":"Linux","color":"E1F5FE","is_active":1}

// Flash
{"error":null,"success":null}

// Nav
{"categories":[{"id":"<uuid>","title":"..."}], "hubsTop":[TagPill]}

// PostItemVM
{
  "id":"<uuid>",
  "content_html":"<p>...</p>",
  "created_iso":"2025-08-24T06:00:00Z",
  "author":{"id":"<uuid>","name":"admin","avatar_url":"/avatars/<uuid>.png","group_label":"member"},
  "metrics":{"rep":0,"posts":10,"tenure_days":123},
  "badge":{"lv":3,"color_hex":"#0ea5e9","bar_bg":"#cfefff","bar_end":"#aee7ff","border":"#0a6aa8"}
}

## BasePageContract

{
  "user": UserLite | null,
  "abilities": AbilityMap,
  "nav": Nav,
  "flash": Flash
}

Поля присутствуют всегда. Если нет данных, значения null или пустые коллекции.
flash не влияет на доступность форм.

##HubPageContract

{
  "hub": {"tag_id":"<uuid>","slug":"linux","title":"Linux"},
  "topics": [{"id":"<uuid>","title":"...","last_post_at":1755989925,"posts_count":2}],
  "pillsTop": [TagPill]
}

Инварианты:

    Темы получены по taggables(entity='topic').

    Сортировка по last_post_at по убыванию.

##CategoryPageContract

{
  "category":{"id":"<uuid>","title":"Тест"},
  "hubsTop":[TagPill],
  "topicsFeatured":[{"id":"<uuid>","title":"..."}]
}

Инварианты:

    hubsTop из tag_stats с ограничением топ-10.

##CreateTopicPageContract

{
  "category":{"id":"<uuid>","title":"..."},
  "canCreate": true,
  "returnTo": "/forum/c/<catId>/create"
}

Результаты действий (пример)

// ReplyResult
{"ok":true,"post_id":"<uuid>","redirect":"<url>"}

Эволюция контрактов

    Новые поля добавляются с дефолтами. Удаление полей проходит через депрекейшн.

    Версия контракта документируется в шапке Actions и ViewModels.

