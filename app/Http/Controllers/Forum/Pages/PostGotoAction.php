<?php // v0.1.5
/* app/Http/Controllers/Forum/Pages/PostGotoAction.php — v0.1.5
Назначение: GET /forum/p/{post_id}/ → редирект на страницу темы с нужной пагинацией и якорем.
FIX: заменён response()->redirect() на корректный ответ с Location-хедером.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\PostLocateService;

final class PostGotoAction
{
    public function __invoke(Request $req, string $post_id): Response
    {
        $perPage = (int)($req->get('per_page') ?? 20);
        if ($perPage < 1)  { $perPage = 20; }
        if ($perPage > 200){ $perPage = 200; }

        $loc = (new PostLocateService())->locate($post_id, $perPage);
        if (!$loc) {
            return response('Post not found', 404);
        }

        $url = '/forum/t/' . rawurlencode((string)$loc['topic_slug'])
             . '/?page=' . (int)$loc['page'] . '#' . (string)$loc['anchor'];

        return response('', 302, ['Location' => $url]);
    }
}
