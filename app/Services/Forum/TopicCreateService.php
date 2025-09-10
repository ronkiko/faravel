    <?php
/* app/Services/Forum/TopicCreateService.php — v0.1.0
Назначение: создать тему из хаба (тега) + первый пост. Подобрать категорию,
назначить теги, обновить счётчики и tag_stats. Без JS. Без DB::update().
FIX: устойчивый выбор категории; генерация slug; редирект на /forum/p/{post_id}.
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class TopicCreateService
{
    /** @return array{topicId:string, topicSlug:string, postId:string} */
    public function createFromHub(string $userId, string $tagSlugOrId, string $title, string $content): array
    {
        $now = time();

        $tag = $this->findTagBySlugOrId($tagSlugOrId);
        if (!$tag) throw new \RuntimeException('Тег не найден');
        $tagId = (string)$tag['id'];

        $catId = $this->pickCategoryIdForTag($tagId);
        if ($catId === '') throw new \RuntimeException('Не удалось определить категорию для тега');

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

        // tag_stats upsert
        $aff = false;
        try {
            $aff = DB::statement(
                "UPDATE tag_stats
                    SET topics_count = topics_count + 1,
                        last_activity_at = ?, updated_at = ?
                  WHERE category_id = ? AND tag_id = ?",
                [$now, $now, $catId, $tagId]
            );
        } catch (\Throwable $e) { /* ignore */ }

        if (!$aff) {
            try {
                DB::table('tag_stats')->insert([
                    'category_id'     => $catId,
                    'tag_id'          => $tagId,
                    'topics_count'    => 1,
                    'last_activity_at'=> $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            } catch (\Throwable $e) { /* ignore */ }
        }

        return ['topicId'=>$topicId, 'topicSlug'=>$topicSlug, 'postId'=>$postId];
    }

    private function findTagBySlugOrId(string $slugOrId): ?array
    {
        $r = DB::table('tags')->where('slug','=',$slugOrId)->first();
        if (!$r) $r = DB::table('tags')->where('id','=',$slugOrId)->first();
        return $r ? (array)$r : null;
    }

    /** Приоритет: category_tag по position ASC → tag_stats по topics_count DESC → первая категория. */
    private function pickCategoryIdForTag(string $tagId): string
    {
        $r = DB::table('category_tag')->select(['category_id'])
            ->where('tag_id','=',$tagId)->orderBy('position','asc')->first();
        if ($r) return (string)$r->category_id;

        $r = DB::table('tag_stats')->select(['category_id'])
            ->where('tag_id','=',$tagId)->orderBy('topics_count','desc')->first();
        if ($r) return (string)$r->category_id;

        $r = DB::table('categories')->select(['id'])->orderBy('order_id','asc')->first();
        if ($r) return (string)$r->id;

        return '';
    }

    private function ensureUniqueTopicSlug(string $slug): string
    {
        if ($slug === '') $slug = bin2hex(random_bytes(4));
        $try = $slug;
        $i = 0;
        while (DB::table('topics')->where('slug','=',$try)->first()) {
            $i++; $try = $slug.'-'.substr(bin2hex(random_bytes(3)), 0, 6);
            if ($i > 10) break;
        }
        return $try;
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~[^\p{L}\p{Nd}]+~u', '-', $s) ?? '';
        $s = trim($s, '-');
        return preg_replace('~\-{2,}~', '-', $s) ?? '';
    }

    private static function uuidV4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $h = bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',
            substr($h,0,8), substr($h,8,4), substr($h,12,4), substr($h,16,4), substr($h,20,12)
        );
    }
}
