<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/EditPostFormAction.php
Назначение: GET /forum/p/{post_id}/edit → форма редактирования поста
FIX: text() → plain make(); вернём 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class EditPostFormAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: EditPostFormAction', 501);
    }
}
