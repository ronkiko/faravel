<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/OpenTopicAction.php
Назначение: POST /forum/t/{topic}/open → открыть тему (модерация)
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class OpenTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: OpenTopicAction', 501);
    }
}
