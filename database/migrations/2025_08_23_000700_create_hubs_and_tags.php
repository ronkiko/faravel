<?php // v0.3.6
/*
database/migrations/2025_08_23_000700_create_hubs_and_tags.php — v0.3.6
Назначение: единая миграция «Хабы/Теги». Перед созданием — удаляет старые таблицы
(tags, taggables, category_tag, tag_stats). Создаёт заново tags/taggables/category_tag/tag_stats;
патчит posts (updated_at, is_deleted); добавляет topics.slug (UNIQUE) для ЧПУ URL
/forum/t/{topic-slug} и комбинированных путей по тегам. Наполнение slug выполняется на уровне
приложения через App\Services\Support\Slugger (TagParser использует Slugger по DI).
FIX: добавлены целевые индексы под реальные запросы: (category_id, position) для category_tag,
(category_id, last_activity_at) для tag_stats, (entity, topic_id) для taggables; сохранены каскады FK.
*/

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<SQL
/* ---------- Очистка: удаляем старые таблицы (порядок: зависимые -> справочники) ---------- */
DROP TABLE IF EXISTS tag_stats;
DROP TABLE IF EXISTS category_tag;
DROP TABLE IF EXISTS taggables;
DROP TABLE IF EXISTS tags;

/* ---------- TAGS: единый справочник тегов ---------- */
CREATE TABLE tags (
  id CHAR(36) NOT NULL,
  slug VARCHAR(64) NOT NULL,
  title VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  color CHAR(6) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  created_by CHAR(36) DEFAULT NULL,
  approved_by CHAR(36) DEFAULT NULL,
  approved_at INT UNSIGNED DEFAULT NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_tags_slug (slug),
  KEY idx_tags_active (is_active),
  CONSTRAINT chk_tags_color CHECK (color IS NULL OR color REGEXP '^[0-9A-Fa-f]{6}$'),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- TAGGABLES: инвертированный индекс присвоений ---------- */
CREATE TABLE taggables (
  tag_id CHAR(36) NOT NULL,
  entity ENUM('topic','post') NOT NULL,
  entity_id CHAR(36) NOT NULL,
  topic_id CHAR(36) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (tag_id, entity, entity_id),
  KEY idx_taggables_tag_topic (tag_id, topic_id),
  KEY idx_taggables_entity (entity, entity_id),
  KEY idx_taggables_entity_topic (entity, topic_id),
  KEY idx_taggables_topic (topic_id),
  CONSTRAINT fk_taggables_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
  CONSTRAINT fk_taggables_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- CATEGORY_TAG: курация тегов на витринах категории ---------- */
CREATE TABLE category_tag (
  category_id CHAR(36) NOT NULL,
  tag_id      CHAR(36) NOT NULL,
  position    INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (category_id, tag_id),
  KEY idx_category_tag_cat_pos (category_id, position),
  CONSTRAINT fk_category_tag_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_category_tag_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- TAG_STATS: материализация счётчиков для «топ-тегов» ---------- */
CREATE TABLE tag_stats (
  category_id      CHAR(36) NOT NULL,
  tag_id           CHAR(36) NOT NULL,
  topics_count     INT UNSIGNED NOT NULL DEFAULT 0,
  last_activity_at INT UNSIGNED DEFAULT NULL,
  created_at       INT UNSIGNED NOT NULL,
  updated_at       INT UNSIGNED NOT NULL,
  PRIMARY KEY (category_id, tag_id),
  KEY idx_tag_stats_last (category_id, last_activity_at),
  CONSTRAINT fk_tag_stats_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_tag_stats_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------- POSTS: патч для редактирования и мягкого удаления ---------- */
ALTER TABLE posts
  ADD COLUMN updated_at INT UNSIGNED NULL AFTER created_at,
  ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at;

UPDATE posts SET updated_at = created_at WHERE updated_at IS NULL;

ALTER TABLE posts
  MODIFY COLUMN updated_at INT UNSIGNED NOT NULL;

/* ---------- TOPICS: человекочитаемый slug для стабильных URL ---------- */
ALTER TABLE topics
  ADD COLUMN slug VARCHAR(160) NULL AFTER title;

ALTER TABLE topics
  ADD UNIQUE KEY uq_topics_slug (slug);
SQL;

        DB::connection()->exec($sql);
    }

    public function down()
    {
        $sql = <<<SQL
/* Откат патча posts */
ALTER TABLE posts DROP COLUMN is_deleted;
ALTER TABLE posts DROP COLUMN updated_at;

/* Откат topics.slug и его индекса */
ALTER TABLE topics DROP INDEX uq_topics_slug;
ALTER TABLE topics DROP COLUMN slug;

/* Удаление таблиц (порядок: зависимые -> справочники) */
DROP TABLE IF EXISTS tag_stats;
DROP TABLE IF EXISTS category_tag;
DROP TABLE IF EXISTS taggables;
DROP TABLE IF EXISTS tags;
SQL;

        DB::connection()->exec($sql);
    }
};
