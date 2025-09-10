<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/DeletePostAction.php
Назначение: POST /forum/p/{post_id}/delete → мягкое удаление поста
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class DeletePostAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: DeletePostAction', 501);
    }
}
