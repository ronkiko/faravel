<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/UpdatePostAction.php
Назначение: POST /forum/p/{post_id}/edit → сохранить изменения поста.
FIX: Switched to explicit Request+typed route param; ready for DI; still returns 501.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;

final class UpdatePostAction
{
    /**
     * Update a post content (placeholder).
     *
     * Layer: Controller. Will validate and delegate to post service.
     *
     * @param Request $request Current HTTP request (expects content).
     * @param string  $post_id Post identifier. Non-empty.
     *
     * Preconditions:
     * - $post_id must be a non-empty string.
     *
     * Side effects:
     * - None in placeholder.
     *
     * @return Response HTTP 501 Not Implemented.
     */
    public function __invoke(Request $request, string $post_id): Response
    {
        return response('Not implemented yet: UpdatePostAction', 501);
    }
}
