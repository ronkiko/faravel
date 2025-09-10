<?php // v0.1.2
/* app/Http/Controllers/Forum/Pages/ReplyFormAction.php — v0.1.2
Назначение: GET /forum/t/{topic_slug}/reply — форма ответа (пока редирект на якорь #reply).
FIX: __invoke(Request $req, string $topic_slug); редирект через response('',302,['Location'=>…]).
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;

final class ReplyFormAction
{
    public function __invoke(Request $req, string $topic_slug): Response
    {
        $url = '/forum/t/' . rawurlencode($topic_slug) . '/#reply';
        return response('', 302, ['Location' => $url]);
    }
}
