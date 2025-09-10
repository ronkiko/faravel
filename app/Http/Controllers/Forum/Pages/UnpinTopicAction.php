<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/UnpinTopicAction.php
Назначение: POST /forum/t/{topic}/unpin → открепить тему
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class UnpinTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: UnpinTopicAction', 501);
    }
}
