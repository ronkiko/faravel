<?php // v0.4.5
/* app/Http/Controllers/Forum/Pages/ReplyToTopicAction.php
Purpose: Принимает POST-ответ в теме. Принимает {id|slug}, проверяет права через
         TopicPolicyContract, создаёт пост, обновляет счётчики темы и tag_stats,
         затем делает redirect обратно в тему с якорем #last.
FIX: Флеш-сообщения пишутся через $request->session()->flash(...) вместо session(),
     чтобы гарантированно переживали redirect и отображались в теме. Остальная
     логика (slug/id, троттлинг, редиректы) без изменений.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Auth;
use App\Contracts\Policies\Forum\TopicPolicyContract;
use App\Services\SettingsService;

final class ReplyToTopicAction
{
    /** @var TopicPolicyContract */
    private TopicPolicyContract $policy;

    public function __construct(TopicPolicyContract $policy)
    {
        $this->policy = $policy;
    }

    /**
     * @param Request $request HTTP form
     * @param string  $id      UUID темы или её slug
     * @return Response
     */
    public function __invoke(Request $request, string $id): Response
    {
        /** @var array<string,mixed>|null $auth */
        $auth = Auth::user();
        $user = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        if ($user === null) {
            $request->session()->flash('error', 'Требуется вход.');
            return redirect($this->safeRedirect((string)$request->input('return_to', ''), $id));
        }
        $userId  = (string)($user['id'] ?? '');
        $groupId = (int)($user['group_id'] ?? 1);

        $topic = $this->findTopicByIdOrSlug($id);
        if (!$topic) {
            $request->session()->flash('error', 'Тема не найдена.');
            return redirect('/forum');
        }

        if (!$this->policy->canReply($user, $topic)) {
            $request->session()->flash('error', 'Нет прав для ответа в этой теме.');
            return redirect($this->safeRedirect(
                (string)$request->input('return_to', ''),
                $topic['id'],
                $topic['slug'] ?? ''
            ));
        }

        // Троттлинг постинга
        $cooldown = $this->resolvePostCooldown($groupId);
        if ($cooldown > 0) {
            /** @var int|null $last */
            $last = DB::scalar(
                "SELECT MAX(created_at) FROM posts WHERE user_id = ? AND is_deleted = 0",
                [$userId]
            );
            $lastTs = is_numeric($last) ? (int)$last : 0;
            if ($lastTs > 0) {
                $delta = time() - $lastTs;
                if ($delta < $cooldown) {
                    $wait = $cooldown - $delta;
                    $request->session()->flash('error', 'Слишком часто. Подождите ' . $wait . ' сек.');
                    return redirect($this->safeRedirect(
                        (string)$request->input('return_to', ''),
                        $topic['id'],
                        $topic['slug'] ?? ''
                    ));
                }
            }
        } elseif ($cooldown < 0) {
            $request->session()->flash('error', 'Постинг запрещён для вашей группы.');
            return redirect($this->safeRedirect(
                (string)$request->input('return_to', ''),
                $topic['id'],
                $topic['slug'] ?? ''
            ));
        }

        // Валидация
        $content = trim((string)$request->input('content', ''));
        if ($content === '') {
            $request->session()->flash('error', 'Сообщение пустое.');
            return redirect($this->safeRedirect(
                (string)$request->input('return_to', ''),
                $topic['id'],
                $topic['slug'] ?? ''
            ));
        }
        if (mb_strlen($content, 'UTF-8') > 10000) {
            $request->session()->flash('error', 'Сообщение слишком длинное.');
            return redirect($this->safeRedirect(
                (string)$request->input('return_to', ''),
                $topic['id'],
                $topic['slug'] ?? ''
            ));
        }

        // Запись
        $now    = time();
        $postId = self::uuidV4();

        DB::table('posts')->insert([
            'id'         => $postId,
            'topic_id'   => (string)$topic['id'],
            'user_id'    => $userId,
            'content'    => $content,
            'created_at' => $now,
            'updated_at' => $now,
            'is_deleted' => 0,
        ]);

        DB::update(
            'UPDATE topics SET posts_count = posts_count + 1, last_post_id=?, last_post_at=?, updated_at=? WHERE id=?',
            [$postId, $now, $now, (string)$topic['id']]
        );

        // best-effort tag_stats
        try {
            $catId = (string)($topic['category_id'] ?? '');
            $hubTagId = (string)(DB::scalar(
                "SELECT tag_id FROM taggables WHERE entity='topic' AND topic_id=? ORDER BY created_at ASC LIMIT 1",
                [$topic['id']]
            ) ?? '');
            if ($catId !== '' && $hubTagId !== '') {
                $aff = DB::statement(
                    'UPDATE tag_stats SET last_activity_at=?, updated_at=? WHERE category_id=? AND tag_id=?',
                    [$now, $now, $catId, $hubTagId]
                );
                if (!$aff) {
                    DB::table('tag_stats')->insert([
                        'category_id'      => $catId,
                        'tag_id'           => $hubTagId,
                        'topics_count'     => 0,
                        'last_activity_at' => $now,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Успех
        $request->session()->flash('success', 'Сообщение опубликовано.');
        $returnTo = (string)$request->input('return_to', '');
        $slug     = (string)($topic['slug'] ?? '');
        return redirect($this->safeRedirect($returnTo, (string)$topic['id'], $slug));
    }

    /** @param string $idOrSlug @return array<string,mixed>|null */
    private function findTopicByIdOrSlug(string $idOrSlug): ?array
    {
        $row = DB::table('topics')->where('id', '=', $idOrSlug)->first();
        if ($row) return (array)$row;
        $row = DB::table('topics')->where('slug', '=', $idOrSlug)->first();
        return $row ? (array)$row : null;
    }

    /** @param string $returnTo @param string $topicId @param string|null $slug @return string */
    private function safeRedirect(string $returnTo, string $topicId, ?string $slug = null): string
    {
        $path = explode('?', $returnTo, 2)[0];
        if (preg_match('~^/forum/t/([a-zA-Z0-9\-]+)/?~', $path) === 1) {
            return rtrim($path, '/') . '/#last';
        }
        $tail = $slug && $slug !== '' ? $slug : $topicId;
        return '/forum/t/' . rawurlencode($tail) . '/#last';
    }

    /** @return string */
    private static function uuidV4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        $h = bin2hex($d);
        return sprintf('%s-%s-%s-%s-%s',
            substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4),
            substr($h, 16, 4), substr($h, 20, 12)
        );
    }

    /** @param int $groupId @return int */
    private function resolvePostCooldown(int $groupId): int
    {
        $text = (string) SettingsService::get('throttle.post.cooldown.groups', '');
        $map  = $this->parseGroupCooldowns($text);
        if (isset($map[$groupId])) {
            return $map[$groupId];
        }
        $def = SettingsService::get('throttle.post.cooldown.default', 60);
        return is_numeric($def) ? (int)$def : 60;
    }

    /** @param string $raw @return array<int,int> */
    private function parseGroupCooldowns(string $raw): array
    {
        $out = [];
        $lines = preg_split('/\r?\n/', $raw) ?: [];
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || (strlen($ln) > 0 && $ln[0] === '#')) {
                continue;
            }
            if (preg_match('/^(\d+)\s*=\s*(-?\d+)$/', $ln, $m) === 1) {
                $gid = (int)$m[1];
                $sec = (int)$m[2];
                if ($sec < -1) { $sec = -1; }
                if ($sec > 86400) { $sec = 86400; }
                $out[$gid] = $sec;
            }
        }
        return $out;
    }
}
