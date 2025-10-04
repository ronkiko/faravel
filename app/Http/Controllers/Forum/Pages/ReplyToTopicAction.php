<?php // v0.4.2
/* app/Http/Controllers/Forum/Pages/ReplyToTopicAction.php
Purpose: Принять POST-ответ в теме, проверить права через политику, создать пост,
         обновить счётчики темы и "последнюю активность", затем сделать redirect.
FIX: Переведён на конструктор-DI: инжектируем TopicPolicyContract; убран вызов
     app(TopicPolicyContract::class).
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Auth;
use App\Contracts\Policies\Forum\TopicPolicyContract;

class ReplyToTopicAction
{
    /** @var \App\Contracts\Policies\Forum\TopicPolicyContract */
    private \App\Contracts\Policies\Forum\TopicPolicyContract $policy;

    /**
     * @param \App\Contracts\Policies\Forum\TopicPolicyContract $policy
     */
    public function __construct(\App\Contracts\Policies\Forum\TopicPolicyContract $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Принять и сохранить ответ в теме.
     *
     * Layer: Controller. Координирует проверку прав через политику, валидацию
     * и делегирует запись в БД. Возвращает redirect с flash-сообщениями.
     *
     * @param Request $request  HTTP-запрос с полями content и return_to.
     * @param string  $topicId  Идентификатор темы; непустая строка UUID/ULID.
     *
     * Preconditions:
     * - Пользователь должен быть авторизован.
     * - Тема с $topicId должна существовать.
     *
     * Side effects:
     * - Модификация БД (insert в posts; update topics, tag_stats).
     * - Модификация сессии (flash).
     *
     * @return Response Redirect на страницу темы с якорем #last.
     * @throws \Throwable При ошибках БД.
     * @example POST /forum/t/1234/reply
     */
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
        if (!$this->policy->canReply($user, $topicArr)) {
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
            // Фолбэк на ручной statement
            try {
                DB::statement(
                    "INSERT INTO posts (id, topic_id, user_id, content, created_at, updated_at, is_deleted)
                     VALUES (?, ?, ?, ?, ?, ?, 0)",
                    [$postId, $topicId, (string)$user['id'], $content, $now, $now]
                );
            } catch (\Throwable $e2) {
                session()->flash('error', 'Не удалось сохранить сообщение.');
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
            // необязательно для успешного ответа
        }

        // Последняя активность по тегам
        try {
            $catId = (string)$topicArr['category_id'];
            if ($catId !== '') {
                $tagRows = DB::table('topic_tags')
                    ->select(['tag_id'])
                    ->where('topic_id', '=', $topicId)
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

    /**
     * Сформировать безопасный URL для редиректа.
     *
     * @param string $returnTo Путь внутри сайта или пусто.
     * @param string $topicId  ID темы.
     *
     * Preconditions:
     * - $returnTo не должен указывать на внешний домен.
     *
     * @return string Абсолютный путь внутри сайта с якорем #last.
     */
    private function safeRedirect(string $returnTo, string $topicId): string
    {
        $fallback = '/forum/t/'.$topicId;
        if ($returnTo !== '' && $returnTo[0] === '/') {
            return $returnTo.'#last';
        }
        return $fallback.'#last';
    }

    /**
     * Сгенерировать UUID v4 для новой записи поста.
     *
     * @return string UUID v4.
     * @throws \Exception Если random_bytes() недоступен.
     */
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
}
