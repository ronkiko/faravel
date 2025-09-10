<?php
/* app/Http/Controllers/Forum/Pages/CreateTopicAction.php — v0.1.0
Назначение: POST /forum/f/{tag_slug}/create — создать тему и первый пост.
FIX: валидация, вызов сервиса, редирект на /forum/p/{post_id}/.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\TopicCreateService;

final class CreateTopicAction
{
    public function __invoke(Request $req, string $tag_slug): Response
    {
        $auth = Auth::user();
        $user = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        if (!$user) return redirect('/login');

        $title   = trim((string)$req->input('title',''));
        $content = trim((string)$req->input('content',''));
        if ($title === '' || $content === '') {
            session()->flash('error', 'Заполните заголовок и текст.');
            return redirect('/forum/f/'.$tag_slug.'/create');
        }

        try {
            $res = (new TopicCreateService())->createFromHub((string)$user['id'], $tag_slug, $title, $content);
        } catch (\Throwable $e) {
            session()->flash('error', 'Ошибка создания темы.');
            return redirect('/forum/f/'.$tag_slug.'/create');
        }

        session()->flash('success', 'Тема создана.');
        return redirect('/forum/p/'.$res['postId'].'/');
    }
}
