<?php // v0.4.7
/* app/Http/Controllers/Forum/Pages/ShowHubAction.php
Purpose: GET /forum/f/{tag_slug}/ — тонкий контроллер хаба: читает query-параметры,
         вызывает сервис и собирает HubPageVM::fromArray().
FIX: Уточнили контракт с сервисом (ожидаем единый topicsForTag()) и аккуратно
     нормализуем pager; используем Request::query() из ядра.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\HubQueryService;
use App\Http\ViewModels\Forum\HubPageVM;
use App\Http\ViewModels\Layout\LayoutNavbarVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ShowHubAction
{
    /**
     * Показ страницы хаба (лента тем по тегу).
     *
     * @param \Faravel\Http\Request $req       Входящий HTTP-запрос.
     * @param string                $tag_slug  Слаг хаба/тега; непустой.
     * @pre Контейнер/сессия/роутинг инициализированы; тег существует.
     * @side-effects Нет (только чтение через сервис).
     * @return \Faravel\Http\Response
     * @throws \Throwable При ошибке сервиса/БД/рендеринга.
     * @example // routes/web.php: Router::get('/forum/f/{tag_slug}', ShowHubAction::class);
     */
    public function __invoke(Request $req, string $tag_slug): Response
    {
        $svc = new HubQueryService();

        $tag = $svc->findTagBySlug($tag_slug);
        if (!$tag) {
            return response()->view('errors.404', [], 404);
        }

        $page    = \max(1, (int)$req->query('page', 1));
        $perPage = 20;
        $sortKey = (string)$req->query('sort', 'last');

        $q = $svc->topicsForTag((string)$tag['id'], $page, $perPage, $sortKey);

        $items   = (array)($q['items'] ?? []);
        $pagerIn = (array)($q['pager'] ?? []);
        $total   = (int)($pagerIn['total'] ?? 0);
        $pages   = (int)($pagerIn['pages'] ?? \max(1, \ceil($total / \max(1, $perPage))));

        $vm = HubPageVM::fromArray([
            'tag'    => $tag,
            'pager'  => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => $pages,
            ],
            'sort'   => ['key' => $sortKey, 'dir' => 'desc'],
            'topics' => $items,
        ]);

        $user = Auth::user();
        $auth = [
            'is_auth'    => (bool)$user,
            'username'   => $user->username ?? ($user['username'] ?? ''),
            'avatar_url' => '',
            'is_admin'   => (bool)($user->is_admin ?? ($user['is_admin'] ?? false)),
        ];

        return response()->view('forum.hub', [
            'vm'     => $vm->toArray(),
            'layout' => [
                'title'  => 'Хаб: ' . (string)$tag['title'],
                'locale' => 'ru',
                'nav'    => LayoutNavbarVM::fromAuth($auth),
            ],
            'flash'  => FlashVM::from([]),
        ]);
    }
}
