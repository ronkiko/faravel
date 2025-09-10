<?php // v0.3.2
/* app/Http/Controllers/Forum/Pages/AddReactionAction.php
Назначение: POST /forum/p/{post_id}/react → добавить реакцию к посту
FIX: text() → plain make(); 501.
*/
namespace App\Http\Controllers\Forum\Pages;
use Faravel\Http\Response;

final class AddReactionAction
{
    public function __invoke(...$args): Response
    {
        return response('Not implemented yet: AddReactionAction', 501);
    }
}
