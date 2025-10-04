<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/PostGotoAction.php — v0.1.5
Назначение: GET /forum/p/{post_id}/ → редирект на страницу темы с нужной
            пагинацией и якорем.
FIX: Переведён на конструктор-DI: инжектируем PostLocateService вместо
     new PostLocateService(). Приведён заголовок к базовой v0.4.x.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\PostLocateService;

final class PostGotoAction
{
    /** @var \App\Services\Forum\PostLocateService */
    private \App\Services\Forum\PostLocateService $loc;

    /**
     * @param \App\Services\Forum\PostLocateService $loc
     */
    public function __construct(\App\Services\Forum\PostLocateService $loc)
    {
        $this->loc = $loc;
    }

    /**
     * Редирект на страницу темы с нужной пагинацией и якорем поста.
     *
     * @param Request $req
     * @param string  $post_id
     *
     * Preconditions:
     * - $post_id непустой.
     * - per_page ограничен: 1..200.
     *
     * @return Response 302 Location на тему или 404.
     */
    public function __invoke(Request $req, string $post_id): Response
    {
        $perPage = (int)($req->get('per_page') ?? 20);
        if ($perPage < 1)  { $perPage = 20; }
        if ($perPage > 200){ $perPage = 200; }

        $loc = $this->loc->locate($post_id, $perPage);
        if (!$loc) {
            return response('Post not found', 404);
        }

        $url = '/forum/t/' . rawurlencode((string)$loc['topic_slug'])
             . '/?page=' . (int)$loc['page'] . '#' . (string)$loc['anchor'];

        return response('', 302, ['Location' => $url]);
    }
}
