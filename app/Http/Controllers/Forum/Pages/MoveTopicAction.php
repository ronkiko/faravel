<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/MoveTopicAction.php
Назначение: POST /forum/t/{topic}/move → перенести тему
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class MoveTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: MoveTopicAction', 501);
    }
}
