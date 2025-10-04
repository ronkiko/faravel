<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/UnpinTopicAction.php
Назначение: POST /forum/t/{topic}/unpin → открепить тему.
FIX: Switched to explicit Request+typed route param; ready for DI; still returns 501.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;

final class UnpinTopicAction
{
    /**
     * Unpin a topic (placeholder).
     *
     * Layer: Controller. Will delegate to moderation service.
     *
     * @param Request $request Current HTTP request.
     * @param string  $topic   Topic identifier or slug. Non-empty.
     *
     * Preconditions:
     * - $topic must be a non-empty string.
     *
     * Side effects:
     * - None in placeholder.
     *
     * @return Response HTTP 501 Not Implemented.
     */
    public function __invoke(Request $request, string $topic): Response
    {
        return response('Not implemented yet: UnpinTopicAction', 501);
    }
}
