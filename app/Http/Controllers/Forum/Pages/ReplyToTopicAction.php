<?php
/* app/Http/Controllers/Forum/Pages/ReplyToTopicAction.php — v0.2.0
Назначение: принять POST-ответ в теме, проверить права, создать пост, обновить
счётчики темы и "последнюю активность" в tag_stats, затем редиректить назад по
безопасному return_to (или на канонический URL темы с якорем #last).
FIX: полная реализация без DB::update(); только insert/statement.
Контракты:
INPUT (POST): content:string, return_to?:string
PATH: /forum/t/{topicId}/reply
OUTPUT: redirect с flash.success|flash.error; без JSON.
Инварианты:
- Требуется авторизация и способность forum.post.create (TopicPolicy::canReply).
- Пустой контент запрещён. UUID поста генерируется локально (v4).
- Обновляются topics.posts_count/last_post_id/last_post_at и tag_stats.last_activity_at.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Auth;
use App\Policies\Forum\TopicPolicy;

class ReplyToTopicAction
{
    public function __invoke(Request $request, string $topicId): Response
    {
        $content  = trim((string)$request->input('content', ''));
        $returnTo = (string)$request->input('return_to', '');
        $now      = time();

        // Пользователь
        $auth = Auth::user();
        $user = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        if ($user === null) {
            session()->flash('error', 'Требуется вход.');
            return redirect($this->safeRedirect($returnTo, $topicId));
        }

        // Тема
        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) {
            session()->flash('error', 'Тема не найдена.');
            return redirect('/forum');
        }
        $topicArr = (array)$topic;

        // Права
        if (!(new TopicPolicy())->canReply($user, $topicArr)) {
            session()->flash('error', 'Нет прав для ответа в эту тему.');
            return redirect($this->safeRedirect($returnTo, $topicId));
        }

        // Валидация
        if ($content === '') {
            session()->flash('error', 'Пустое сообщение.');
            return redirect($this->safeRedirect($returnTo, $topicId));
        }

        // Создание поста
        $postId = self::uuidV4();
        try {
            // Пытаемся через QueryBuilder
            DB::table('posts')->insert([
                'id'         => $postId,
                'topic_id'   => $topicId,
                'user_id'    => (string)$user['id'],
                'content'    => $content,
                'created_at' => $now,
                'updated_at' => $now,
                'is_deleted' => 0,
            ]);
        } catch (\Throwable $e) {
            // Фоллбэк на ручной statement (на случай отсутствия insert() в обёртке)
            try {
                DB::statement(
                    "INSERT INTO posts (id, topic_id, user_id, content, created_at, updated_at, is_deleted)
                     VALUES (?, ?, ?, ?, ?, ?, 0)",
                    [$postId, $topicId, (string)$user['id'], $content, $now, $now]
                );
            } catch (\Throwable $e2) {
                session()->flash('error', 'Ошибка сохранения сообщения.');
                return redirect($this->safeRedirect($returnTo, $topicId));
            }
        }

        // Обновление счётчиков темы
        try {
            DB::statement(
                "UPDATE topics
                   SET posts_count = posts_count + 1,
                       last_post_id = ?,
                       last_post_at = ?,
                       updated_at   = ?
                 WHERE id = ?",
                [$postId, $now, $now, $topicId]
            );
        } catch (\Throwable $e) {
            // мягко игнорируем: счётчики поправим отдельно, если потребуется
        }

        // Обновление last_activity_at для всех тегов темы в tag_stats
        try {
            $catId = (string)($topicArr['category_id'] ?? '');
            if ($catId !== '') {
                $tagRows = DB::table('taggables')
                    ->select(['tag_id'])
                    ->where('entity', '=', 'topic')
                    ->where('entity_id', '=', $topicId)
                    ->get();
                foreach ($tagRows as $r) {
                    $tid = (string)$r->tag_id;
                    if ($tid !== '') {
                        DB::statement(
                            "UPDATE tag_stats
                                SET last_activity_at = ?, updated_at = ?
                              WHERE category_id = ? AND tag_id = ?",
                            [$now, $now, $catId, $tid]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // необязательно для успешного ответа
        }

        session()->flash('success', 'Ответ добавлен.');
        return redirect($this->safeRedirect($returnTo, $topicId));
    }

    private function safeRedirect(string $returnTo, string $topicId): string
    {
        $fallback = '/forum/t/'.$topicId;
        // только внутренняя переадресация
        if ($returnTo !== '' && $returnTo[0] === '/') {
            return $returnTo.'#last';
        }
        return $fallback.'#last';
    }

    private static function uuidV4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $h = bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',
            substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12)
        );
    }
}
