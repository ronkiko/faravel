<?php // v0.4.3
/* app/Http/Controllers/Forum/Pages/CreateTopicAction.php
Purpose: POST /forum/f/{tag_slug}/create — создать тему и первый пост через сервис.
FIX: Переведено логирование на App\Support\Logger с тегами в стиле ядра.
     Убраны сторонние фасады; flash только через session()->flash(...).
*/

namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\TopicCreateService;
use App\Support\Logger;

final class CreateTopicAction
{
    /** @var TopicCreateService Сервис создания темы и первого поста. */
    private TopicCreateService $svc;

    /**
     * @param TopicCreateService $svc Сервис доменного слоя.
     */
    public function __construct(TopicCreateService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * Создать тему в указанном хабе и первый пост.
     *
     * Layer: Controller. Валидирует ввод, проверяет авторизацию, делегирует сервису.
     *
     * @param Request $req      HTTP-запрос с полями title и content.
     * @param string  $tag_slug Слаг хаба/тега. Непустая строка.
     *
     * Preconditions:
     * - Пользователь авторизован (Auth::user()['id'] непустой).
     * - Поля title и content — непустые строки.
     *
     * Side effects:
     * - session()->flash('error'|'success'|'title'|'content', string)
     * - Изменения в БД внутри TopicCreateService.
     *
     * @return Response Redirect на форму при ошибке или на страницу поста при успехе.
     *
     * @throws \Throwable Пробрасывает непредвиденные ошибки сервиса.
     */
    public function __invoke(Request $req, string $tag_slug): Response
    {
        Logger::log('TOPIC.CREATE.START', 'tag=' . $tag_slug);

        // 1) Авторизация
        $auth   = Auth::user(); // array{id:string,...}|null
        $userId = is_array($auth) ? (string)($auth['id'] ?? '') : '';
        Logger::log('TOPIC.CREATE.AUTH', 'userId=' . ($userId !== '' ? $userId : ''));
        if ($userId === '') {
            session()->flash('error', 'Требуется вход.');
            Logger::log('TOPIC.CREATE.REDIRECT', '/login');
            return redirect('/login');
        }

        // 2) Ввод
        $title   = trim((string)$req->input('title', ''));
        $content = trim((string)$req->input('content', ''));
        Logger::log(
            'TOPIC.CREATE.INPUT',
            'title_len=' . mb_strlen($title) . ' content_len=' . mb_strlen($content)
        );

        // 3) Валидация
        $errors = [];
        if ($title === '' || mb_strlen($title) < 3) {
            $errors[] = 'Укажите заголовок не короче 3 символов.';
        }
        if ($content === '' || mb_strlen($content) < 10) {
            $errors[] = 'Текст сообщения должен быть не короче 10 символов.';
        }
        if (!empty($errors)) {
            $msg = implode(' ', $errors);
            session()->flash('error', $msg);
            session()->flash('title', $title);
            session()->flash('content', $content);
            $to = '/forum/f/' . $tag_slug . '/create';
            Logger::log('TOPIC.CREATE.VALIDATION_FAIL', $msg);
            Logger::log('TOPIC.CREATE.REDIRECT', $to);
            return redirect($to);
        }

        // 4) Вызов доменного сервиса
        try {
            Logger::log('TOPIC.CREATE.SERVICE_CALL', 'user=' . $userId . ' tag=' . $tag_slug);
            /** @var array{postId:string,topicId:string} $res */
            $res = $this->svc->createFromHub($userId, $tag_slug, $title, $content);
            Logger::log('TOPIC.CREATE.SERVICE_OK', 'postId=' . ($res['postId'] ?? ''));
        } catch (\Throwable $e) {
            session()->flash('error', 'Ошибка создания темы.');
            session()->flash('title', $title);
            session()->flash('content', $content);
            Logger::exception('TOPIC.CREATE', $e, [
                'userId' => $userId,
                'tag'    => $tag_slug,
            ]);
            $to = '/forum/f/' . $tag_slug . '/create';
            Logger::log('TOPIC.CREATE.REDIRECT', $to);
            return redirect($to);
        }

        // 5) Успех → редирект на первый пост
        session()->flash('success', 'Тема создана.');
        $to = '/forum/p/' . $res['postId'] . '/';
        Logger::log('TOPIC.CREATE.SUCCESS', 'redirect=' . $to);
        return redirect($to);
    }
}
