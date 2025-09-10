<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/UpdateTopicTagsAction.php
Назначение: POST /forum/t/{topic}/tags → обновление тегов темы
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class UpdateTopicTagsAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: UpdateTopicTagsAction', 501);
    }
}
