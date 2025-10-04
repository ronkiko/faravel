<?php // v0.4.2
/* app/Http/Controllers/AdminForumController.php
Purpose: Админ-CRUD форумов и связей с категориями. Рендерит единый Blade
         resources/views/admin/forums.blade.php со списком и формой.
FIX: Исключены несуществующие в схеме поля (parent_id, is_visible, is_locked,
     min_group) из INSERT/UPDATE. Упрощён вывод: в Blade печатаются только
     скаляры. Передаём $csrf как строку вместо {{ $layout['csrf'] }}.
*/

namespace App\Http\Controllers;

use App\Services\Auth\AdminVisibilityPolicy;
use App\Services\Layout\LayoutService;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;

final class AdminForumController
{
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /**
     * Проверка доступа в админку форумов.
     *
     * @return Response|null 403/302 или null если доступ разрешён.
     */
    protected function authorizeManage(): ?Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/login');
        }
        $roleId = (int)($u['role_id'] ?? 0);

        if (method_exists($this->adminPolicy, 'canManageForums')) {
            return $this->adminPolicy->canManageForums($roleId) ? null : response('Forbidden', 403);
        }
        return $this->adminPolicy->canAccessAdmin($roleId) ? null : response('Forbidden', 403);
    }

    /**
     * Список+форма создания.
     */
    public function index(Request $request): Response
    {
        if ($r = $this->authorizeManage()) {
            return $r;
        }

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Форумы',
            'nav_active' => 'admin',
        ])->toArray();

        // Читаем форумы без полей, которых нет в схеме.
        $rows = DB::table('forums')
            ->orderBy('order_id', 'asc')
            ->get();

        $forums = [];
        foreach ($rows as $row) {
            $arr = (array)$row;
            $id  = (string)($arr['id'] ?? '');
            $forums[] = [
                'title'            => (string)($arr['title'] ?? ''),
                'slug'             => (string)($arr['slug'] ?? ''),
                'parent_title'     => '—', // parent_id в схеме нет
                'order_id'         => (int)($arr['order_id'] ?? 0),
                'visible_text'     => 'yes',  // поле отсутствует — считаем видимым
                'locked_text'      => 'no',   // поле отсутствует — считаем открытым
                'min_group_label'  => 'guest',
                'edit_url'         => '/admin/forums/' . $id . '/edit',
                'delete_url'       => '/admin/forums/' . $id . '/delete',
            ];
        }

        // Опции «родитель» (схема без parent_id — показываем «нет»)
        $parentOptions = []; // оставлено для совместимости разметки

        // Опции групп — минимально: guest/member/admin
        $groupOptions = [
            ['value' => 'guest',  'name' => 'guest',  'selected' => true],
            ['value' => 'member', 'name' => 'member', 'selected' => false],
            ['value' => 'admin',  'name' => 'admin',  'selected' => false],
        ];

        $s = $request->session();
        $hasErr = (bool)$s->get('error');
        $hasOk  = (bool)$s->get('success');

        return response()->view('admin.forums', [
            // список
            'forums'         => $forums,

            // форма создания
            'is_edit'        => false,
            'form_action'    => '/admin/forums',
            'value_id'       => '',
            'value_title'    => (string)($s->get('old_title') ?? ''),
            'value_slug'     => (string)($s->get('old_slug') ?? ''),
            'value_order'    => 1,
            'visible_checked'=> false,
            'locked_checked' => false,
            'parent_options' => $parentOptions,
            'group_options'  => $groupOptions,
            'value_delete_url' => '',

            // layout+csrf и флэши
            'layout'        => $layout,
            'csrf'          => (string)$layout['csrf'],
            'has_error'     => $hasErr,
            'flash_error'   => (string)($s->get('error') ?? ''),
            'has_success'   => $hasOk,
            'flash_success' => (string)($s->get('success') ?? ''),
        ]);
    }

    /**
     * Показ формы редактирования.
     */
    public function edit(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) {
            return $r;
        }

        $row = DB::table('forums')->where('id', '=', $id)->first();
        if (!$row) {
            return response('Not found', 404);
        }
        $f = (array)$row;

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Форумы',
            'nav_active' => 'admin',
        ])->toArray();

        $s = $request->session();
        $hasErr = (bool)$s->get('error');
        $hasOk  = (bool)$s->get('success');

        return response()->view('admin.forums', [
            'forums'         => $this->listForView(),
            'is_edit'        => true,
            'form_action'    => '/admin/forums/' . $id,
            'value_id'       => (string)$id,
            'value_title'    => (string)($f['title'] ?? ''),
            'value_slug'     => (string)($f['slug'] ?? ''),
            'value_order'    => (int)($f['order_id'] ?? 1),
            'visible_checked'=> false,
            'locked_checked' => false,
            'parent_options' => [],
            'group_options'  => [
                ['value' => 'guest',  'name' => 'guest',  'selected' => true],
                ['value' => 'member', 'name' => 'member', 'selected' => false],
                ['value' => 'admin',  'name' => 'admin',  'selected' => false],
            ],
            'value_delete_url' => '/admin/forums/' . $id . '/delete',

            'layout'        => $layout,
            'csrf'          => (string)$layout['csrf'],
            'has_error'     => $hasErr,
            'flash_error'   => (string)($s->get('error') ?? ''),
            'has_success'   => $hasOk,
            'flash_success' => (string)($s->get('success') ?? ''),
        ]);
    }

    /**
     * POST /admin/forums — создать.
     */
    public function store(Request $request): Response
    {
        if ($r = $this->authorizeManage()) {
            return $r;
        }

        $raw = $request->only(['title', 'slug', 'description', 'order_id']);
        $v   = Validator::make($raw, [
            'title' => 'required|string|min:3|max:100',
            'slug'  => 'nullable|string|min:2|max:50',
            'description' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            $request->session()->flash('error', 'Проверьте поля');
            $request->session()->put('old_title', (string)($raw['title'] ?? ''));
            $request->session()->put('old_slug', (string)($raw['slug'] ?? ''));
            return redirect('/admin/forums');
        }

        DB::table('forums')->insert([
            'id'          => \App\Services\Support\IdGenerator::uuidv4(),
            'slug'        => (string)($raw['slug'] ?? ''),
            'title'       => (string)$raw['title'],
            'description' => (string)($raw['description'] ?? ''),
            'order_id'    => (int)($raw['order_id'] ?? 1),
            'created_at'  => time(),
        ]);

        $request->session()->flash('success', 'Форум создан.');
        return redirect('/admin/forums');
    }

    /**
     * POST /admin/forums/{id} — обновить.
     */
    public function update(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) {
            return $r;
        }

        $raw = $request->only(['title', 'slug', 'description', 'order_id']);
        DB::table('forums')->where('id', '=', $id)->update([
            'title'       => (string)($raw['title'] ?? ''),
            'slug'        => (string)($raw['slug'] ?? ''),
            'description' => (string)($raw['description'] ?? ''),
            'order_id'    => (int)($raw['order_id'] ?? 1),
        ]);

        $request->session()->flash('success', 'Сохранено.');
        return redirect('/admin/forums');
    }

    /**
     * POST /admin/forums/{id}/delete — удалить.
     */
    public function destroy(Request $request, string $id): Response
    {
        if ($r = $this->authorizeManage()) {
            return $r;
        }
        DB::table('forums')->where('id', '=', $id)->delete();
        $request->session()->flash('success', 'Удалено.');
        return redirect('/admin/forums');
    }

    /**
     * Служебно: получить список для index/edit.
     *
     * @return array<int,array<string,string|int>>
     */
    private function listForView(): array
    {
        $rows = DB::table('forums')->orderBy('order_id', 'asc')->get();
        $out = [];
        foreach ($rows as $row) {
            $a = (array)$row;
            $id = (string)($a['id'] ?? '');
            $out[] = [
                'title'            => (string)($a['title'] ?? ''),
                'slug'             => (string)($a['slug'] ?? ''),
                'parent_title'     => '—',
                'order_id'         => (int)($a['order_id'] ?? 0),
                'visible_text'     => 'yes',
                'locked_text'      => 'no',
                'min_group_label'  => 'guest',
                'edit_url'         => '/admin/forums/' . $id . '/edit',
                'delete_url'       => '/admin/forums/' . $id . '/delete',
            ];
        }
        return $out;
    }
}
