<?php
/* app/Http/Controllers/Forum/Pages/ShowTopicAction.php — v0.2.0
Показ темы. Сбор сырья из БД, построение PostItemVM[], TopicPageVM и передача
в Blade. Для совместимости до шага 11 отдаёт и «legacy»-поля ($topic,$category,$posts).

FIX: реализованы выборки темы/постов/пилюль, групповой fetch авторов и их метрик,
canReply через TopicPolicy, returnTo из Request.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Auth;
use App\Policies\Forum\TopicPolicy;
use App\Http\ViewModels\Forum\PostItemVM;
use App\Http\ViewModels\Forum\TopicPageVM;

class ShowTopicAction
{
    public function __invoke(Request $request, ?string $tagId = null, string $topicId = ''): Response
    {
        // 1) Тема
        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) {
            return response()->view('errors.404', [], 404);
        }
        $topic = (array)$topic;

        // 2) Категория
        $category = null;
        if (!empty($topic['category_id'])) {
            $category = DB::table('categories')->select(['id','title'])->where('id','=',$topic['category_id'])->first();
            $category = $category ? (array)$category : null;
        }

        // 3) Посты темы (сырые, для Legacy-рендера)
        $postsRaw = DB::table('posts')
            ->where('topic_id','=',$topicId)
            ->where('is_deleted','=',0)
            ->orderBy('created_at','asc')
            ->get();
        $postsRaw = array_map(fn($r)=>(array)$r, $postsRaw ?? []);

        // 4) Авторы одним пакетом
        $uids = array_values(array_unique(array_filter(array_map(fn($p)=> (string)($p['user_id'] ?? ''), $postsRaw))));
        $users = [];
        if ($uids) {
            $rows = DB::table('users')->whereIn('id', $uids)->get();
            foreach ($rows as $r) $users[(string)$r->id] = (array)$r;
        }

        // 5) Лейблы групп
        $groups = [];
        if ($users) {
            $gids = array_values(array_unique(array_filter(array_map(fn($u)=> (int)($u['group_id'] ?? 0), $users))));
            if ($gids) {
                $gr = DB::table('groups')->whereIn('id',$gids)->get();
                foreach ($gr as $g) $groups[(int)$g->id] = (string)($g->name ?? 'group '.$g->id);
            }
        }

        // 6) Кол-во постов по авторам
        $postsCountByUser = [];
        if ($uids) {
            $cnt = DB::table('posts')->selectRaw('user_id, COUNT(*) AS c')->whereIn('user_id',$uids)->groupBy('user_id')->get();
            foreach ($cnt as $r) $postsCountByUser[(string)$r->user_id] = (int)$r->c;
        }

        // 7) Пилюли тегов темы
        $tagPills = [];
        $tagRows = DB::table('taggables as tg')
            ->join('tags as t','t.id','=','tg.tag_id')
            ->select(['t.slug','t.title','t.color','t.is_active'])
            ->where('tg.entity','=','topic')
            ->where('tg.entity_id','=',$topicId)
            ->orderBy('t.title','asc')
            ->get();
        foreach ($tagRows as $r) {
            $tagPills[] = [
                'slug'      => (string)$r->slug,
                'title'     => (string)$r->title,
                'color'     => (string)($r->color ?? ''),
                'is_active' => (int)$r->is_active,
            ];
        }

        // 8) ViewModel постов
        $now = time();
        $postVMs = [];
        foreach ($postsRaw as $p) {
            $uid = (string)($p['user_id'] ?? '');
            $u   = $users[$uid] ?? [];
            if ($u) {
                $u['group_label'] = $groups[(int)($u['group_id'] ?? 0)] ?? ('Группа '.(int)($u['group_id'] ?? 0));
            }
            $postVMs[] = PostItemVM::fromRaw($p, $u, [
                'posts_count'   => $postsCountByUser[$uid] ?? 0,
                'base_url_user' => '/u',
                'now_ts'        => $now,
            ]);
        }

        // 9) Политика: можно ли отвечать
        $auth = Auth::user();
        $userLite = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        $canReply = (new TopicPolicy())->canReply($userLite, $topic);

        // 10) returnTo
        $returnTo = (string)($request->server('REQUEST_URI') ?? '/forum');

        // 11) TopicPageVM
        $vm = TopicPageVM::from(
            base: [], // базовые поля уже шарятся компоузером
            topic: $topic,
            postsVm: $postVMs,
            tagPills: $tagPills,
            canReply: $canReply,
            returnTo: $returnTo
        );

        // 12) Совместимость со старым Blade (до шага 11)
        $viewData = [
            // Legacy:
            'topic'      => $topic,
            'category'   => $category,
            'posts'      => $postsRaw,
            'tagPills'   => $tagPills,
            'canReply'   => $canReply,
            'content'    => (string)($request->old('content') ?? ''),
            'warning'    => (string)(session()->get('error') ?? ''),
            // Новое:
            'vm'         => $vm->toArray(),
            'postStyle'  => 'classic',
        ];

        return response()->view('forum.topic', $viewData);
    }
}
