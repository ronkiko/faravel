<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/RemoveReactionAction.php
Назначение: POST /forum/p/{post_id}/react/remove → убрать реакцию
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class RemoveReactionAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: RemoveReactionAction', 501);
    }
}
