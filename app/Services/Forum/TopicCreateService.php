<?php // v0.4.2
/* app/Services/Forum/TopicCreateService.php
Purpose: Доменный сервис создания темы из хаба (тега) и первого поста. Подбирает
категорию, проставляет связи и обновляет счётчики, включая tag_stats.
FIX: Шапка приведена к строгим требованиям PHP: namespace сразу после тега.
Добавлены PHPDoc по контракту методов. Без вывода до namespace.
*/

namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class TopicCreateService
{
    /**
     * Создать тему в хабе и первый пост.
     *
     * Layer: Service. Инкапсулирует транзакционную бизнес-операцию без побочных
     * эффектов вне БД. Валидация входа — на контроллере.
     *
     * @param string $userId        Идентификатор автора. Непустая строка.
     * @param string $tagSlugOrId   Слаг или ID тега. Непустая строка.
     * @param string $title         Заголовок темы. Непустая строка.
     * @param string $content       Тело первого поста. Непустая строка.
     *
     * Preconditions:
     * - Все параметры непустые строки.
     * - Существуют записи в таблицах tags, categories.
     *
     * Side effects:
     * - Пишет в таблицы topics, posts, taggables, tag_stats.
     * - Обновляет counters в topics (statement).
     *
     * @return array{topicId:string, topicSlug:string, postId:string}
     *
     * @throws \RuntimeException если не найден тег или не определена категория.
     *
     * @example
     *  $res = $svc->createFromHub('u1','linux','Hello','Body');
     *  // ['topicId'=>'...','topicSlug'=>'hello','postId'=>'...']
     */
    public function createFromHub(
        string $userId,
        string $tagSlugOrId,
        string $title,
        string $content
    ): array {
        $now = time();

        $tag = $this->findTagBySlugOrId($tagSlugOrId);
        if (!$tag) {
            throw new \RuntimeException('Тег не найден');
        }
        $tagId = (string) $tag['id'];

        $catId = $this->pickCategoryIdForTag($tagId);
        if ($catId === '') {
            throw new \RuntimeException('Не удалось определить категорию для тега');
        }

        $topicId   = self::uuidV4();
        $topicSlug = $this->ensureUniqueTopicSlug($this->slugify($title));

        // topic
        DB::table('topics')->insert([
            'id'           => $topicId,
            'category_id'  => $catId,
            'forum_id'     => null,
            'user_id'      => $userId,
            'title'        => $title,
            'slug'         => $topicSlug,
            'posts_count'  => 0,
            'last_post_id' => null,
            'last_post_at' => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // first post
        $postId = self::uuidV4();
        DB::table('posts')->insert([
            'id'         => $postId,
            'topic_id'   => $topicId,
            'user_id'    => $userId,
            'content'    => $content,
            'created_at' => $now,
            'updated_at' => $now,
            'is_deleted' => 0,
        ]);

        // counters
        DB::statement(
            "UPDATE topics
               SET posts_count = posts_count + 1,
                   last_post_id = ?, last_post_at = ?, updated_at = ?
             WHERE id = ?",
            [$postId, $now, $now, $topicId]
        );

        // taggable (topic)
        DB::table('taggables')->insert([
            'tag_id'     => $tagId,
            'entity'     => 'topic',
            'entity_id'  => $topicId,
            'topic_id'   => $topicId,
            'created_at' => $now,
        ]);

        // tag_stats upsert (update or insert)
        $aff = false;
        try {
            $aff = DB::statement(
                "UPDATE tag_stats
                    SET topics_count = topics_count + 1,
                        last_activity_at = ?, updated_at = ?
                  WHERE category_id = ? AND tag_id = ?",
                [$now, $now, $catId, $tagId]
            );
        } catch (\Throwable $e) {
            /* ignore */
        }

        if (!$aff) {
            try {
                DB::table('tag_stats')->insert([
                    'category_id'      => $catId,
                    'tag_id'           => $tagId,
                    'topics_count'     => 1,
                    'last_activity_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            } catch (\Throwable $e) {
                /* ignore */
            }
        }

        return ['topicId' => $topicId, 'topicSlug' => $topicSlug, 'postId' => $postId];
    }

    /**
     * Найти тег по слагу или ID.
     *
     * @param string $slugOrId Слаг или UUID тега.
     * @return array<string,mixed>|null Ассоц-массив строки тега или null.
     */
    private function findTagBySlugOrId(string $slugOrId): ?array
    {
        $r = DB::table('tags')->where('slug', '=', $slugOrId)->first();
        if (!$r) {
            $r = DB::table('tags')->where('id', '=', $slugOrId)->first();
        }
        return $r ? (array) $r : null;
    }

    /**
     * Выбрать category_id для тега.
     *
     * Приоритет:
     *  1) category_tag по position ASC,
     *  2) tag_stats по topics_count DESC,
     *  3) первая категория по order_id ASC.
     *
     * @param string $tagId UUID тега.
     * @return string UUID категории или пустая строка.
     */
    private function pickCategoryIdForTag(string $tagId): string
    {
        $r = DB::table('category_tag')->select(['category_id'])
            ->where('tag_id', '=', $tagId)->orderBy('position', 'asc')->first();
        if ($r) {
            return (string) $r->category_id;
        }

        $r = DB::table('tag_stats')->select(['category_id'])
            ->where('tag_id', '=', $tagId)->orderBy('topics_count', 'desc')->first();
        if ($r) {
            return (string) $r->category_id;
        }

        $r = DB::table('categories')->select(['id'])->orderBy('order_id', 'asc')->first();
        if ($r) {
            return (string) $r->id;
        }

        return '';
    }

    /**
     * Сгенерировать уникальный слаг темы, добавляя случайный хвост при коллизии.
     *
     * @param string $slug Кандидат.
     * @return string Уникальный slug.
     */
    private function ensureUniqueTopicSlug(string $slug): string
    {
        if ($slug === '') {
            $slug = bin2hex(random_bytes(4));
        }
        $try = $slug;
        $i = 0;
        while (DB::table('topics')->where('slug', '=', $try)->first()) {
            $i++;
            $try = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if ($i > 10) {
                break;
            }
        }
        return $try;
    }

    /**
     * Простейшая транслітерация в slug.
     *
     * @param string $s Исходная строка.
     * @return string slug.
     */
    private function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~[^\p{L}\p{Nd}]+~u', '-', $s) ?? '';
        $s = trim($s, '-');
        return preg_replace('~\-{2,}~', '-', $s) ?? '';
    }

    /**
     * UUID v4.
     *
     * @return string
     * @throws \Exception при ошибке random_bytes().
     */
    private static function uuidV4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $h = bin2hex($d);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 12, 4),
            substr($h, 16, 4),
            substr($h, 20, 12)
        );
    }
}
