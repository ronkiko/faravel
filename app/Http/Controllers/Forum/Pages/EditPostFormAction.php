<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/EditPostFormAction.php
Purpose: GET /forum/p/{post_id}/edit — форма редактирования поста. Заглушка 501.
FIX: Приведено к базовой версии 0.4.x; добавлен строгий PHPDoc и стабильная сигнатура.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Response;

final class EditPostFormAction
{
    /**
     * Показать форму редактирования поста (заглушка).
     *
     * @param array<int,string> $args route-параметры.
     *
     * @return Response 501.
     */
    public function __invoke(string ...$args): Response
    {
        return response('Not implemented yet: EditPostFormAction', 501);
    }
}
