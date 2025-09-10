<?php // v0.1.2
/* app/Http/Controllers/Forum/Pages/CreateTopicFormAction.php
Назначение: GET /forum/f/{tag_slug}/create → форма создания темы в выбранном хабе.
FIX: обёртка над существующим ShowCreateTopicAction для единообразия нейминга.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Http\Controllers\Forum\Pages\ShowCreateTopicAction;

final class CreateTopicFormAction
{
    public function __invoke(Request $req, string $tag_slug): Response
    {
        $delegate = new ShowCreateTopicAction();
        return $delegate($req, $tag_slug);
    }
}
