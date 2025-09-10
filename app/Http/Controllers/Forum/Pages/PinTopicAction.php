<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/PinTopicAction.php
Назначение: POST /forum/t/{topic}/pin → закрепить тему
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class PinTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: PinTopicAction', 501);
    }
}
