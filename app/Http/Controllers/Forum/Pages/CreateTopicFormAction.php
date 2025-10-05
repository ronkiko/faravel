<?php // v0.4.3
/* app/Http/Controllers/Forum/Pages/CreateTopicFormAction.php
Purpose: страница создания темы (GET). Контроллер готовит минимальный VM, ожидаемый
шаблоном resources/views/forum/create_topic.blade.php, без внешних зависимостей.
FIX: Переведено чтение флеша на session()->old('key'): error, success, title, content.
     Убраны любые fallback'и и обращения к has()/get() для флеша.
*/

namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;

final class CreateTopicFormAction
{
    /**
     * Показ формы создания темы для хаба по слагу.
     *
     * Layer: Controller. Готовит VM для View, без бизнес-логики и внешних вызовов.
     *
     * @param Request $request  HTTP-запрос.
     * @param string  $tag_slug Слаг хаба. Непустая строка.
     *
     * Preconditions:
     * - $tag_slug !== ''.
     *
     * Side effects:
     * - Чтение flash через session()->old().
     *
     * @return Response HTML-ответ с формой.
     *
     * @example
     *  // GET /forum/f/linux/create
     *  // Вернёт форму с action=/forum/f/linux/create, поля предзаполнятся из flash.
     */
    public function __invoke(Request $request, string $tag_slug): Response
    {
        $s = $request->session();

        $vm = [
            'links' => [
                'forum' => '/forum',
                'hub'   => "/forum/f/{$tag_slug}",
            ],
            'tag' => [
                'title' => $this->humanizeTag($tag_slug),
                'slug'  => $tag_slug,
            ],
            'flash' => [
                'error'       => (string) $s->old('error', ''),
                'success'     => (string) $s->old('success', ''),
                'has_error'   => $s->old('error', '')   !== '',
                'has_success' => $s->old('success', '') !== '',
            ],
            'form' => [
                'action' => "/forum/f/{$tag_slug}/create",
            ],
            'draft' => [
                'title'   => (string) $s->old('title', ''),
                'content' => (string) $s->old('content', ''),
            ],
        ];

        return response()->view('forum.create_topic', ['vm' => $vm]);
    }

    /**
     * Convert slug to a human-readable title. No I/O.
     *
     * @param string $slug Non-empty slug.
     * @return string Title-cased tag.
     *
     * @example humanizeTag('linux') => 'Linux'
     */
    private function humanizeTag(string $slug): string
    {
        $s = str_replace(['-', '_'], ' ', $slug);
        $s = trim($s);
        return $s !== '' ? mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1) : 'Tag';
    }
}
