<?php // v0.3.9
/*
database/migrations/2025_08_23_000955_drop_forums_immediate.php v0.3.9
Назначение: мгновенно убрать устаревшую схему форумов на пустой базе — дропнуть таблицы
forums и category_forum и удалить колонку topics.forum_id (с FK/индексами, если были).

FIX: добавлены «мягкие» попытки снять FK/индексы с разных возможных имён; удалены таблицы
forums/category_forum; колонка forum_id удалена из topics. В down() — минимальное
восстановление схемы для fresh-setup (без данных).
*/

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $exec = fn(string $sql) => DB::connection()->exec($sql);
        $try  = function (string $sql) use ($exec) { try { $exec($sql); } catch (\Throwable $e) {} };

        /* --- снять возможные FK/индексы у topics.forum_id --- */
        $try("ALTER TABLE topics DROP FOREIGN KEY fk_topics_forum_id");
        $try("ALTER TABLE topics DROP FOREIGN KEY topics_forum_id_foreign");
        $try("ALTER TABLE topics DROP INDEX idx_topics_forum_id");
        $try("ALTER TABLE topics DROP INDEX topics_forum_id_index");

        /* --- удалить колонку forum_id --- */
        $try("ALTER TABLE topics DROP COLUMN forum_id");

        /* --- дропнуть устаревшие таблицы (сначала зависимая) --- */
        $try("DROP TABLE IF EXISTS category_forum");
        $try("DROP TABLE IF EXISTS forums");
    }

    public function down()
    {
        $exec = fn(string $sql) => DB::connection()->exec($sql);

        /* --- восстановим минимальные таблицы (без данных) --- */
        $exec(<<<SQL
CREATE TABLE IF NOT EXISTS forums (
  id CHAR(36) NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $exec(<<<SQL
CREATE TABLE IF NOT EXISTS category_forum (
  category_id CHAR(36) NOT NULL,
  forum_id    CHAR(36) NOT NULL,
  position    INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (category_id, forum_id),
  KEY idx_cf_pos (category_id, position),
  CONSTRAINT fk_cf_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_cf_forum FOREIGN KEY (forum_id) REFERENCES forums(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        /* --- вернём колонку topics.forum_id (без жёстких FK, чтобы не мешать fresh) --- */
        try { $exec("ALTER TABLE topics ADD COLUMN forum_id CHAR(36) NULL AFTER category_id"); } catch (\Throwable $e) {}
        try { $exec("ALTER TABLE topics ADD INDEX idx_topics_forum_id (forum_id)"); } catch (\Throwable $e) {}
    }
};
