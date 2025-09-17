<?php // v0.4.1
/* app/Http/Controllers/AdminCategoryController.php
Purpose: Админ-CRUD категорий (index/create/edit).
FIX: Во все view добавлен 'layout' через LayoutService (nav_active=admin).
*/

namespace App\Http\Controllers;

use App\Services\Auth\AdminVisibilityPolicy;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use App\Services\Layout\LayoutService;

final class AdminCategoryController
{
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Доступ к управлению категориями */
    protected function authorizeManage(): ?Response
    {
        $u = Auth::user();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (method_exists($this->adminPolicy, 'canManageCategories')) {
            if (!$this->adminPolicy->canManageCategories($roleId)) {
                return response('Forbidden', 403);
            }
        } elseif (!$this->adminPolicy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    public function index(Request $request): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $cats = DB::select("SELECT id, slug, title, description, order_id FROM categories ORDER BY order_id ASC");

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Категории',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.categories', [
            'categories' => $cats,
            'layout'     => $layout,
            'flash'      => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $raw = $request->only(['title','slug','description']);
        $v = Validator::make($raw, [
            'title' => 'required|string|min:3|max:100',
            'slug'  => 'nullable|string|min:2|max:50',
            'description' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            $request->session()->flash('error','Проверьте поля');
            return redirect('/admin/categories');
        }

        $title = (string)$raw['title'];
        $baseSlug = (string)($raw['slug'] ?? '');
        $desc = (string)($raw['description'] ?? '');

        // next order
        $maxOrder = (int)(DB::table('categories')->max('order_id') ?? 0);
        $nextOrder = $maxOrder + 1;

        $uuid = \App\Services\Support\IdGenerator::uuidv4();
        $slug = $baseSlug !== '' ? $baseSlug : 'cat';

        // ensure unique slug
        for ($i=0; $i<50; $i++) {
            try {
                DB::table('categories')->insert([
                    'id'          => $uuid,
                    'slug'        => $slug,
                    'title'       => $title,
                    'description' => ($desc === '' ? null : $desc),
                    'order_id'    => $nextOrder,
                ]);
                $request->session()->flash('success', 'Категория создана.');
                return redirect('/admin/categories');
            } catch (\Throwable $e) {
                $slug = \App\Support\Slugger::make($baseSlug !== '' ? $baseSlug : $title) . '-' . ($i+2);
            }
        }

        $request->session()->flash('error','Не удалось создать категорию.');
        return redirect('/admin/categories');
    }

    public function edit(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $cat = DB::table('categories')->where('id','=',$id)->first();
        if (!$cat) { return response('Not found', 404); }

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Категории',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.categories', [
            'edit'   => (array)$cat,
            'layout' => $layout,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $raw = $request->only(['title','slug','description']);
        DB::table('categories')->where('id','=',$id)->update([
            'title'       => (string)$raw['title'],
            'slug'        => (string)($raw['slug'] ?? ''),
            'description' => (string)($raw['description'] ?? ''),
        ]);
        $request->session()->flash('success','Сохранено.');
        return redirect('/admin/categories');
    }

    public function destroy(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }
        DB::table('categories')->where('id','=',$id)->delete();
        $request->session()->flash('success','Удалено.');
        return redirect('/admin/categories');
    }
}
