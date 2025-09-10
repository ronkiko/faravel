<?php // v0.4.1
/* app/Http/Controllers/Forum/Pages/ShowCategoryAction.php
Purpose: GET /forum/c/{category_slug}/ — category page render. Thin controller.
FIX: Switched to CategoryPageVM::fromArray() + standardized auth.username.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Forum\CategoryQueryService;
use App\Http\ViewModels\Forum\CategoryPageVM;
use App\Http\ViewModels\Layout\LayoutNavbarVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ShowCategoryAction
{
    /**
     * @param \Faravel\Http\Request $req
     * @param string $category_slug
     * @return \Faravel\Http\Response
     */
    public function __invoke(Request $req, string $category_slug): Response
    {
        $svc = new CategoryQueryService();
        $cat = $svc->findCategoryBySlug($category_slug);
        if (!$cat) {
            return response()->view('errors.404', [], 404);
        }

        $hubsTop = $svc->topTagsForCategory((string)$cat['id'], 10);
        $vm = CategoryPageVM::fromArray([
            'category' => $cat,
            'hubsTop'  => $hubsTop,
        ]);

        $user = Auth::user();
        $auth = [
            'is_auth'    => (bool)$user,
            'username'   => $user->username ?? ($user['username'] ?? ''),
            'avatar_url' => '',
            'is_admin'   => (bool)($user->is_admin ?? ($user['is_admin'] ?? false)),
        ];

        return response()->view('forum.category', [
            'vm'     => $vm->toArray(),
            'layout' => [
                'title'  => 'Категория: ' . (string)$cat['title'],
                'locale' => 'ru',
                'nav'    => LayoutNavbarVM::fromAuth($auth),
            ],
            'flash'  => FlashVM::from([]),
        ]);
    }
}
