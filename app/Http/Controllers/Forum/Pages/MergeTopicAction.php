<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/MergeTopicAction.php
Назначение: POST /forum/t/{topic}/merge → объединить тему
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class MergeTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: MergeTopicAction', 501);
    }
}
