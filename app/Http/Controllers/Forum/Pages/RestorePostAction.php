<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/RestorePostAction.php
Назначение: POST /forum/p/{post_id}/restore → восстановление поста
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class RestorePostAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: RestorePostAction', 501);
    }
}
