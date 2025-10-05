<?php // v0.4.142
/* app/Http/Controllers/Forum/Pages/ShowTopicAction.php
Purpose: Показ темы по slug|id. Тонкий контроллер: сервис → VM → view.
FIX: rawurldecode slug; поиск строго через findTopicBySlugOrId(); добавлен «хаб»
     (первый тег) в хлебные крошки: Форум › Категория › Хаб › Тема.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\TopicQueryService;
use App\Http\ViewModels\Layout\FlashVM;
use App\Contracts\Policies\Forum\TopicPolicyContract;
use App\Http\ViewModels\Forum\PostItemVM;
use App\Http\ViewModels\Forum\TopicPageVM;
use App\Support\Format\TimeFormatter;

final class ShowTopicAction
{
    private TopicQueryService $svc;
    private TopicPolicyContract $policy;

    public function __construct(
        TopicQueryService $svc,
        TopicPolicyContract $policy
    ) {
        $this->svc    = $svc;
        $this->policy = $policy;
    }

    /** @return Response */
    public function __invoke(Request $request, string $key): Response
    {
        $svc = $this->svc;

        // Разрешаем кириллицу/пробелы и т.п. в slug
        $key = rawurldecode($key);

        /** @var array<string,mixed>|null $topic */
        $topic = $svc->findTopicBySlugOrId($key);
        if (!$topic) {
            return response()->view('errors.404', [], 404);
        }

        $topicId  = (string) $topic['id'];
        $category = $svc->findCategoryLight((string) ($topic['category_id'] ?? ''));
        $postsRaw = $svc->listPosts($topicId, 100);

        // Сопутствующие данные к постам
        $uids        = $svc->pluckUserIds($postsRaw);
        $usersById   = $svc->fetchByIds('users', 'id', $uids, ['id', 'username', 'group_id']);
        $gids        = $svc->pluckGroupIds($usersById);
        $groupsById  = $svc->fetchByIds('groups', 'id', $gids, ['id', 'name']);
        $groupLabels = [];
        foreach ($groupsById as $gid => $g) {
            $groupLabels[(int)$gid] = (string)($g['name'] ?? ('group ' . (int)$gid));
        }
        $postsCountByUser = $svc->countPostsByUserFromArray($postsRaw);
        $tagPills         = $svc->listTagsForTopic($topicId);

        // PostItemVM → массивы для Blade
        $now          = time();
        $postVMsArray = [];
        foreach ($postsRaw as $p) {
            $uid = (string)($p['user_id'] ?? '');
            $u   = (array)($usersById[$uid] ?? []);
            $gid = (int)($u['group_id'] ?? 0);

            $postVMsArray[] = PostItemVM::fromArray([
                'id'          => (string)($p['id'] ?? ''),
                'content'     => (string)($p['content'] ?? ''),
                'created_at'  => (int)($p['created_at'] ?? 0),
                'created_ago' => TimeFormatter::humanize((int)($p['created_at'] ?? 0), $now),
                'user'        => [
                    'id'          => $uid,
                    'username'    => (string)($u['username'] ?? 'user'),
                    'group_label' => (string)($groupLabels[$gid] ?? 'member'),
                    'posts_count' => (int)($postsCountByUser[$uid] ?? 0),
                ],
            ])->toArray();
        }

        // Политика + фолбэк на факт входа
        $auth       = Auth::user();
        $userLite   = is_array($auth) ? $auth : (is_object($auth) ? (array)$auth : null);
        $canByPol   = $this->policy->canReply($userLite, $topic);
        $isLoggedIn = is_array($userLite) && !empty($userLite['id']);
        $canReply   = (bool)($canByPol || $isLoggedIn);

        // Сборка breadcrumbs (+хаб как первый тег)
        $catSlug  = (string)($category['slug'] ?? '');
        $hubSlug  = '';
        $hubTitle = '';
        if (!empty($tagPills)) {
            $first    = (array)$tagPills[0];
            $hubSlug  = (string)($first['slug']  ?? '');
            $hubTitle = (string)($first['title'] ?? '');
        }

        $breadcrumbs = [
            ['title' => 'Форум', 'url' => '/forum'],
            [
                'title' => (string)($category['title'] ?? ''),
                'url'   => $catSlug !== '' ? '/forum/c/' . rawurlencode($catSlug) . '/' : '/forum',
            ],
        ];
        if ($hubSlug !== '' && $hubTitle !== '') {
            $breadcrumbs[] = [
                'title' => $hubTitle,
                'url'   => '/forum/f/' . rawurlencode($hubSlug) . '/',
            ];
        }
        $breadcrumbs[] = ['title' => (string)$topic['title'], 'url' => ''];

        $replyUrl = '/forum/t/' . rawurlencode((string)($topic['slug'] ?? $topicId)) . '/reply';

        $vm = TopicPageVM::fromArray([
            'topic' => [
                'id'             => $topicId,
                'slug'           => (string)($topic['slug'] ?? $topicId),
                'title'          => (string)$topic['title'],
                'category_slug'  => $catSlug,
                'category_title' => (string)($category['title'] ?? ''),
            ],
            'posts'       => $postVMsArray,
            'pagination'  => [
                'page'      => 1,
                'per_page'  => max(1, count($postVMsArray)),
                'total'     => (int)count($postVMsArray),
                'last_page' => 1,
            ],
            'abilities'   => ['can_reply' => $canReply],
            'can_reply'   => $canReply,
            'links'       => ['reply' => $replyUrl],
            'breadcrumbs' => $breadcrumbs,
            'meta' => [
                'return_to' => (string)($request->server('REQUEST_URI') ?? '/forum'),
                'tags'      => $tagPills,
            ],
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        return response()->view('forum.topic', [
            'vm'               => $vm->toArray(),
            'postStyle'        => 'classic',
            'layout_overrides' => [
                'title'      => 'Тема: ' . (string)$topic['title'],
                'nav_active' => 'forum',
            ],
            'flash'            => $flash,
        ]);
    }
}
