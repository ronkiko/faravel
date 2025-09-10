<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/ShowCreateTopicAction.php
Purpose: GET /forum/f/{tag_slug}/create — show create-topic form VM.
FIX: Switched to CreateTopicPageVM::fromArray() + standardized auth only indirectly (layout VM).
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use App\Services\Forum\TopicCreateService;
use App\Http\ViewModels\Forum\CreateTopicPageVM;

final class ShowCreateTopicAction
{
    /**
     * @param \Faravel\Http\Request $req
     * @param string $tag_slug
     * @return \Faravel\Http\Response
     */
    public function __invoke(Request $req, string $tag_slug): Response
    {
        $row = DB::table('tags')->select(['id','slug','title','color'])
            ->where('slug','=',$tag_slug)->first();
        if (!$row) {
            return response()->view('errors.404', [], 404);
        }
        $tag = (array)$row;

        // Resolve category via service rule (no DB in VM/Blade)
        $catId = (new \ReflectionClass(TopicCreateService::class))
            ->getMethod('pickCategoryIdForTag')->invoke(new TopicCreateService(), (string)$tag['id']);
        $cat = ['id'=>'','slug'=>'','title'=>'Категория'];
        if ($catId !== '') {
            $c = DB::table('categories')->select(['id','slug','title'])
                ->where('id','=',$catId)->first();
            if ($c) $cat = (array)$c;
        }

        $vm = CreateTopicPageVM::fromArray([
            'tag'          => ['id'=>$tag['id'],'slug'=>$tag['slug'],
                               'title'=>$tag['title'],'color'=>$tag['color'] ?? ''],
            'category'     => $cat,
            'postUrl'      => '/forum/f/'.(string)$tag['slug'].'/create',
            'draftTitle'   => (string)$req->input('title',''),
            'draftContent' => (string)$req->input('content',''),
        ]);

        return response()->view('forum.create_topic', ['vm' => $vm->toArray()]);
    }
}
