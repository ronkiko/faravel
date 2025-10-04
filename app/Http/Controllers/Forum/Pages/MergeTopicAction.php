<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/MergeTopicAction.php
Purpose: POST /forum/t/{topic}/merge — объединить тему. Заглушка 501.
FIX: Приведено к базовой версии 0.4.x; добавлен строгий PHPDoc и стабильная сигнатура.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Response;

final class MergeTopicAction
{
    /**
     * Объединить темы (заглушка).
     *
     * @param array<int,string> $args route-параметры.
     * @return Response 501.
     */
    public function __invoke(string ...$args): Response
    {
        return response('Not implemented yet: MergeTopicAction', 501);
    }
}
