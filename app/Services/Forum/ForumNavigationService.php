<?php
/* app/Services/Forum/ForumNavigationService.php v0.1.0
Назначение: вычисления навигации по постам и темам (страница, якорь, URL).
FIX: добавлен resolveForPostId(): по post_id вычисляет канонический URL темы. */
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class ForumNavigationService
{
    /** Вернёт: ['topicId','topicSlug','page','anchor','#url'] */
    public function resolveForPostId(string $postId): array
    {
        $post = DB::table('posts')->select(['id','topic_id','created_at','is_deleted'])->where('id','=',$postId)->first();
        if (!$post || (int)$post->is_deleted === 1) {
            throw new \RuntimeException('Post not found');
        }

        $topic = DB::table('topics')->select(['id','slug'])->where('id','=',(string)$post->topic_id)->first();
        if (!$topic) {
            throw new \RuntimeException('Topic not found');
        }

        $pp = 20;
        $opt = DB::table('settings')->select(['value'])->where('key','=','forum.posts.per_page')->first();
        if ($opt && is_numeric($opt->value)) $pp = max(1, (int)$opt->value);

        $posRow = DB::select(
            "SELECT COUNT(*) AS c FROM posts WHERE topic_id=? AND is_deleted=0 AND created_at<=?",
            [(string)$post->topic_id, (int)$post->created_at]
        );
        $pos  = (int)($posRow[0]->c ?? 1);
        $page = (int)ceil($pos / $pp);

        $slug = (string)($topic->slug ?? '');
        $base = '/forum/t/'.($slug !== '' ? $slug : (string)$topic->id).'/';
        $url  = $base.($page>1 ? ('?page='.$page) : '').'#p'.$postId;

        return [
            'topicId'   => (string)$topic->id,
            'topicSlug' => $slug,
            'page'      => $page,
            'anchor'    => '#p'.$postId,
            'url'       => $url,
        ];
    }
}
