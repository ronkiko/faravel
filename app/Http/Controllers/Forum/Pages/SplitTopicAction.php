<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/SplitTopicAction.php
Назначение: POST /forum/t/{topic}/split → разделить тему
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class SplitTopicAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: SplitTopicAction', 501);
    }
}
