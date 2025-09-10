<?php
/* app/Services/Forum/PostLocateService.php — v0.1.0
Назначение: найти тему/страницу/якорь по post_id без логики в роутере.
FIX: вычисляет позицию среди не удалённых постов и номер страницы.
Контракт:
in: postId:string, perPage:int>=1
out: array{topic_id,topic_slug,position,page,anchor}
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class PostLocateService
{
    /** @return array{topic_id:string,topic_slug:string,position:int,page:int,anchor:string}|null */
    public function locate(string $postId, int $perPage = 20): ?array
    {
        $row = DB::select("SELECT p.id, p.topic_id, p.created_at, t.slug
                             FROM posts p
                             INNER JOIN topics t ON t.id = p.topic_id
                            WHERE p.id = ? LIMIT 1", [$postId]);
        if (!$row) return null;
        $r = (array)$row[0];
        $topicId = (string)($r['topic_id'] ?? $r['topic_id'] ?? '');
        $created = (int)($r['created_at'] ?? 0);
        $slug    = (string)($r['slug'] ?? '');

        // позиция среди НЕ удалённых по времени
        $cnt = DB::select(
            "SELECT COUNT(*) AS c
               FROM posts
              WHERE topic_id = ? AND is_deleted = 0 AND created_at <= ?",
            [$topicId, $created]
        );
        $pos  = (int)($cnt[0]->c ?? $cnt[0]['c'] ?? 0);
        $page = max(1, (int)ceil($pos / max(1, $perPage)));
        return [
            'topic_id'   => $topicId,
            'topic_slug' => $slug !== '' ? $slug : $topicId,
            'position'   => $pos,
            'page'       => $page,
            'anchor'     => 'p'.$postId,
        ];
    }
}
