<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/CreateTopicAction.php
Purpose: POST /forum/f/{tag_slug}/create — создать тему и первый пост.
FIX: Убран new TopicCreateService(); внедрение через конструктор (DI). Добавлен PHPDoc.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\TopicCreateService;

final class CreateTopicAction
{
    /** @var TopicCreateService */
    private TopicCreateService $svc;

    /**
     * @param TopicCreateService $svc Сервис создания темы и первого поста.
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
     * @param Request $req       HTTP-запрос (title, content).
     * @param string  $tag_slug  Слаг хаба/тега.
     *
     * Preconditions:
     * - Пользователь должен быть авторизован.
     * - title и content — непустые строки.
     *
     * Side effects:
     * - Пишет flash-сообщения в сессию.
     * - Модифицирует БД через TopicCreateService.
     *
     * @return Response Redirect на форму при ошибке или на страницу поста при успехе.
     *
     * @throws \Throwable Пробрасывает непредвиденные ошибки сервиса.
     *
     * @example POST /forum/f/php/create  body:{title,content}
     */
    public function __invoke(Request $req, string $tag_slug): Response
    {
        $auth = Auth::user();
        $user = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        if ($user === null || ($user['id'] ?? '') === '') {
            session()->flash('error', 'Требуется вход.');
            return redirect('/forum/f/' . $tag_slug . '/create');
        }

        $title   = trim((string)$req->input('title', ''));
        $content = trim((string)$req->input('content', ''));

        if ($title === '' || $content === '') {
            session()->flash('error', 'Заполните заголовок и текст.');
            return redirect('/forum/f/' . $tag_slug . '/create');
        }

        try {
            /** @var array{postId:string,topicId:string} $res */
            $res = $this->svc->createFromHub((string)$user['id'], $tag_slug, $title, $content);
        } catch (\Throwable $e) {
            session()->flash('error', 'Ошибка создания темы.');
            return redirect('/forum/f/' . $tag_slug . '/create');
        }

        session()->flash('success', 'Тема создана.');
        return redirect('/forum/p/' . $res['postId'] . '/');
    }
}
