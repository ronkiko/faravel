<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/CloseTopicAction.php
Назначение: POST /forum/t/{topic}/close → закрыть тему (модерация)
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class CloseTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: CloseTopicAction', 501);
    }
}
