<?php // v0.4.1
/* app/Http/Controllers/AdminForumController.php
Purpose: Админ-CRUD форумов и связей с категориями.
FIX: Передан 'layout' через LayoutService во все view (nav_active=admin).
*/

namespace App\Http\Controllers;

use App\Services\Auth\AdminVisibilityPolicy;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use App\Services\Layout\LayoutService;

final class AdminForumController
{
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    protected function authorizeManage(): ?Response
    {
        $u = Auth::user();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (method_exists($this->adminPolicy, 'canManageForums')) {
            if (!$this->adminPolicy->canManageForums($roleId)) {
                return response('Forbidden', 403);
            }
            return null;
        }
        return $this->adminPolicy->canAccessAdmin($roleId) ? null : response('Forbidden', 403);
    }

    public function index(Request $request): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $forums = DB::select("
          SELECT id, slug, title, description, order_id
          FROM forums
          ORDER BY order_id ASC
        ");

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Форумы',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.forums.index', [
            'forums' => $forums,
            'layout' => $layout,
        ]);
    }

    public function create(Request $request): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Форумы',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.forums.form', [
            'layout' => $layout,
        ]);
    }

    public function store(Request $request): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $raw = $request->only(['title','slug','description']);
        $v   = Validator::make($raw, [
            'title' => 'required|string|min:3|max:100',
            'slug'  => 'nullable|string|min:2|max:50',
            'description' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            $request->session()->flash('error','Проверьте поля');
            return redirect('/admin/forums');
        }

        $now = time();
        $uuid = \App\Services\Support\IdGenerator::uuidv4();
        DB::table('forums')->insert([
            'id'          => $uuid,
            'slug'        => (string)($raw['slug'] ?? ''),
            'title'       => (string)$raw['title'],
            'description' => (string)($raw['description'] ?? ''),
            'order_id'    => (int)((DB::table('forums')->max('order_id') ?? 0) + 1),
            'created_at'  => $now,
        ]);

        $request->session()->flash('success','Форум создан.');
        return redirect('/admin/forums');
    }

    public function edit(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $forum = DB::table('forums')->where('id','=',$id)->first();
        if (!$forum) { return response('Not found', 404); }

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Форумы',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.forums.form', [
            'edit'   => (array)$forum,
            'layout' => $layout,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }

        $raw = $request->only(['title','slug','description']);
        DB::table('forums')->where('id','=',$id)->update([
            'title'       => (string)$raw['title'],
            'slug'        => (string)($raw['slug'] ?? ''),
            'description' => (string)($raw['description'] ?? ''),
        ]);
        $request->session()->flash('success','Сохранено.');
        return redirect('/admin/forums');
    }

    public function destroy(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) { return $r; }
        DB::table('forums')->where('id','=',$id)->delete();
        $request->session()->flash('success','Удалено.');
        return redirect('/admin/forums');
    }
}
