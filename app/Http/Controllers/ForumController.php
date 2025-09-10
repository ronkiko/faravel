<?php // v0.4.2
/* app/Http/Controllers/Forum/ForumController.php
Purpose: Тонкий фасад над Pages\*Action для форумных роутов. Делегирование без логики.
FIX: Сделан явный nullable тип у $tagId (?string), чтобы убрать deprecation
     «Implicitly nullable parameters are deprecated».
*/
namespace App\Http\Controllers\Forum;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Http\Controllers\Forum\Pages\ShowTopicAction;
use App\Http\Controllers\Forum\Pages\ReplyToTopicAction;

class ForumController
{
    /**
     * Делегат отображения топика (или списка по тегу).
     *
     * @param Request $request
     * @param ?string $tagId   Может отсутствовать в маршруте.
     * @param string  $topicId Пустая строка допустима для индекса по тегу.
     * @return Response
     */
    public function showTopic(Request $request, ?string $tagId = null, string $topicId = ''): Response
    {
        return (new ShowTopicAction())($request, $tagId, $topicId);
    }

    /**
     * Делегат ответа в топике.
     *
     * @param Request $request
     * @param string  $topicId
     * @return Response
     */
    public function reply(Request $request, string $topicId): Response
    {
        return (new ReplyToTopicAction())($request, $topicId);
    }
}
