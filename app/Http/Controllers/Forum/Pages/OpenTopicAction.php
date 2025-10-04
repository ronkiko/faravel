<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/OpenTopicAction.php
Purpose: POST /forum/t/{topic}/open — открыть тему (модерация). Заглушка 501.
FIX: Приведено к базовой версии 0.4.x; добавлен строгий PHPDoc и стабильная сигнатура.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Response;

final class OpenTopicAction
{
    /**
     * Открыть тему (заглушка).
     *
     * @param array<int,string> $args route-параметры.
     * @return Response 501.
     */
    public function __invoke(string ...$args): Response
    {
        return response('Not implemented yet: OpenTopicAction', 501);
    }
}
