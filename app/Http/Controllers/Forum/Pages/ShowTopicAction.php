<?php // v0.4.139
/* app/Http/Controllers/Forum/Pages/ShowTopicAction.php
Purpose: Показ темы по slug|id. Тонкий контроллер: сервис → VM → view. Подсветку навигации
         задаём через layout_overrides['nav_active']='forum'.
FIX: Переименован humanizeAgo → общий App\Support\Format\TimeFormatter::format_time();
     удалён локальный хелпер. Вызовы обновлены.
*/

namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\TopicQueryService;
use App\Http\ViewModels\Layout\FlashVM;
use App\Policies\Forum\TopicPolicy;
use App\Http\ViewModels\Forum\PostItemVM;
use App\Http\ViewModels\Forum\TopicPageVM;
use App\Support\Format\TimeFormatter;

final class ShowTopicAction
{
    /**
     * Показ страницы темы.
     *
     * Controller → Service → VM → View: контроллер координирует, бизнес-логики нет.
     *
     * @param Request $request   Текущий HTTP-запрос.
     * @param string  $topic_slug Слаг или ID темы; непустой.
     * @pre $topic_slug !== ''.
     * @side-effects Чтение БД (TopicQueryService); чтение сессии (FlashVM).
     * @return Response HTML 200 или 404 при отсутствии темы.
     * @throws \Throwable Ошибки сервисов/рендера пробрасываются.
     * @example GET /forum/t/ustanovka-arch-linux/
     */
    public function __invoke(Request $request, string $topic_slug): Response
    {
        $svc   = new TopicQueryService();

        /** @var array<string,mixed>|null $topic */
        $topic = $svc->findTopicBySlugOrId($topic_slug);
        if (!$topic) {
            return response()->view('errors.404', [], 404);
        }

        /** @var array<string,mixed> $topic */
        $topicId  = (string) $topic['id'];
        $category = $svc->findCategoryLight((string) ($topic['category_id'] ?? ''));
        $postsRaw = $svc->listPosts($topicId, 100);

        // Сопутствующие данные к постам.
        $uids        = $svc->pluckUserIds($postsRaw);
        $usersById   = $svc->fetchByIds('users', 'id', $uids, ['id', 'username', 'group_id']);
        $gids        = $svc->pluckGroupIds($usersById);
        $groupsById  = $svc->fetchByIds('groups', 'id', $gids, ['id', 'name']);
        $groupLabels = [];
        foreach ($groupsById as $gid => $g) {
            $groupLabels[(int) $gid] = (string) ($g['name'] ?? ('group ' . (int) $gid));
        }
        $postsCountByUser = $svc->countPostsByUserFromArray($postsRaw);
        $tagPills         = $svc->listTagsForTopic($topicId);

        // PostItemVM → массивы для Blade.
        $now          = time();
        $postVMsArray = [];
        foreach ($postsRaw as $p) {
            $uid = (string) ($p['user_id'] ?? '');
            $u   = (array) ($usersById[$uid] ?? []);
            $gid = (int) ($u['group_id'] ?? 0);

            $postVMsArray[] = PostItemVM::fromArray([
                'id'          => (string) ($p['id'] ?? ''),
                'content'     => (string) ($p['content'] ?? ''),
                'created_at'  => (int) ($p['created_at'] ?? 0),
                'created_ago' => TimeFormatter::humanize((int) ($p['created_at'] ?? 0), $now),
                'user'        => [
                    'id'          => (string) $uid,
                    'username'    => (string) ($u['username'] ?? 'user'),
                    'group_label' => (string) ($groupLabels[$gid] ?? 'member'),
                    'posts_count' => (int) ($postsCountByUser[$uid] ?? 0),
                ],
            ])->toArray();
        }

        // Политика + фолбэк на факт входа.
        $auth       = Auth::user();
        $userLite   = is_array($auth) ? $auth : (is_object($auth) ? (array) $auth : null);
        $canByPol   = (new TopicPolicy())->canReply($userLite, $topic);
        $isLoggedIn = is_array($userLite) && isset($userLite['id']) && (string) $userLite['id'] !== '';
        $canReply   = (bool) ($canByPol || $isLoggedIn);

        $catSlug  = (string) ($category['slug'] ?? '');
        $replyUrl = '/forum/t/' . rawurlencode((string) ($topic['slug'] ?? $topicId)) . '/reply';

        $vm = TopicPageVM::fromArray([
            'topic' => [
                'id'             => $topicId,
                'slug'           => (string) ($topic['slug'] ?? $topicId),
                'title'          => (string) $topic['title'],
                'category_slug'  => $catSlug,
                'category_title' => (string) ($category['title'] ?? ''),
            ],
            'posts'       => $postVMsArray,
            'pagination'  => [
                'page'      => 1,
                'per_page'  => max(1, count($postVMsArray)),
                'total'     => (int) count($postVMsArray),
                'last_page' => 1,
            ],
            'abilities'   => ['can_reply' => $canReply],
            'can_reply'   => $canReply,
            'links'       => ['reply' => $replyUrl],
            'breadcrumbs' => [
                ['title' => 'Форум', 'url' => '/forum'],
                [
                    'title' => (string) ($category['title'] ?? ''),
                    'url'   => $catSlug !== '' ? '/forum/c/' . rawurlencode($catSlug) . '/' : '/forum',
                ],
                ['title' => (string) $topic['title'], 'url' => ''],
            ],
            'meta' => [
                'return_to' => (string) ($request->server('REQUEST_URI') ?? '/forum'),
                'tags'      => $tagPills,
            ],
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        // DEBUG
        /* 
        die(debug([
            'vm'               => $vm->toArray(),
            'postStyle'        => 'classic',
            'layout_overrides' => [
                'title'      => 'Тема: ' . (string) $topic['title'],
                'nav_active' => 'forum',
            ],
            'flash'            => $flash,
        ]));
        */

        return response()->view('forum.topic', [
            'vm'               => $vm->toArray(),
            'postStyle'        => 'classic',
            'layout_overrides' => [
                'title'      => 'Тема: ' . (string) $topic['title'],
                'nav_active' => 'forum',
            ],
            'flash'            => $flash,
        ]);
    }
}
