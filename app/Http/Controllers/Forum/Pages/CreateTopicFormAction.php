<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/CreateTopicFormAction.php
Purpose: GET /forum/f/{tag_slug}/create — показать форму создания темы.
FIX: Делегирование в ShowCreateTopicAction через конструктор-DI.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;

final class CreateTopicFormAction
{
    /** @var ShowCreateTopicAction */
    private ShowCreateTopicAction $showCreate;

    /**
     * @param ShowCreateTopicAction $showCreate Делегат показа формы.
     */
    public function __construct(ShowCreateTopicAction $showCreate)
    {
        $this->showCreate = $showCreate;
    }

    /**
     * Показать форму создания темы выбранного хаба.
     *
     * @param Request $req
     * @param string  $tag_slug
     *
     * @return Response HTML-страница формы.
     */
    public function __invoke(Request $req, string $tag_slug): Response
    {
        return $this->showCreate->__invoke($req, $tag_slug);
    }
}
