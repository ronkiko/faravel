<?php
// v0.3.2 — AdminAbilityController (single view + group by first token)
// FIX: Жёсткие «потолочные» проверки в edit/update/delete по текущей записи.
//      Нельзя открывать/редактировать/удалять записи с min_role выше роли текущего админа.

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use Faravel\Support\Facades\Auth;
use App\Services\Auth\AbilityService;
use App\Services\Auth\AdminVisibilityPolicy;

class AdminAbilityController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Список (просмотр доступен всем с доступом в админку) */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorizeView()) { return $resp; }

        $abilities = DB::select("
            SELECT id, name, label, description, min_role
            FROM abilities
            ORDER BY name ASC
        ");

        $rolesAll     = DB::select("SELECT id, name, label FROM roles ORDER BY id ASC");
        $roleLabels   = $this->roleLabelsMap($rolesAll);
        $rolesSelect  = $this->filterRolesByCeiling($rolesAll, $this->currentRoleId());
        $groups       = $this->groupByTopPrefix($abilities, $roleLabels);
        $canManage    = $this->adminPolicy->canManageAbilities($this->currentRoleId());

        $tabParam = (string)$request->get('tab', '');
        $tab = ($tabParam === 'create') ? 'create' : 'index';
        if (!$canManage) { $tab = 'index'; }

        return response()->view('admin.abilities', [
            'groups' => $groups,
            'roles'  => $rolesSelect,
            'perm'   => ['can_manage' => $canManage],
            'tab'    => $tab,
            'flash'  => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    /** Форма создания (та же вьюха, вкладка create) */
    public function create(Request $request): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $abilities   = DB::select("
            SELECT id, name, label, description, min_role
            FROM abilities
            ORDER BY name ASC
        ");
        $rolesAll    = DB::select("SELECT id, name, label FROM roles ORDER BY id ASC");
        $roleLabels  = $this->roleLabelsMap($rolesAll);
        $rolesSelect = $this->filterRolesByCeiling($rolesAll, $this->currentRoleId());

        return response()->view('admin.abilities', [
            'groups' => $this->groupByTopPrefix($abilities, $roleLabels),
            'roles'  => $rolesSelect,
            'perm'   => ['can_manage' => true],
            'tab'    => 'create',
            'flash'  => [
                'error'   => $request->session()->get('error'),
                'success' => $request->session()->get('success'),
            ],
        ]);
    }

    /** Создание записи */
    public function store(Request $request): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $data = [
            'name'        => trim((string)$request->input('name')),
            'label'       => trim((string)$request->input('label')),
            'description' => trim((string)$request->input('description')),
            'min_role'    => (int)$request->input('min_role'),
        ];

        $v = Validator::make($data, [
            'name'        => ['required','string','min:3','max:100'],
            'label'       => ['nullable','string','max:100'],
            'description' => ['nullable','string','max:10000'],
            'min_role'    => ['required','integer','min:-128','max:127'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $list) { $flat = array_merge($flat, $list); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities?tab=create', 302);
        }

        // Потолок роли при создании
        $myRole = $this->currentRoleId();
        if ($data['min_role'] > $myRole) {
            $request->session()->flash(
                'error',
                "Недостаточно прав: min_role выше вашей (min_role={$data['min_role']}, ваш role_id={$myRole})."
            );
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities?tab=create', 302);
        }

        // Уникальность name
        $exists = (int)DB::scalar("SELECT COUNT(*) FROM abilities WHERE name = ?", [$data['name']]);
        if ($exists) {
            $request->session()->flash('error', 'Ability с таким name уже существует.');
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities?tab=create', 302);
        }

        $now = time();
        DB::insert(
            "INSERT INTO abilities (name, label, description, min_role, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$data['name'], $data['label'], $data['description'], $data['min_role'], $now, $now]
        );

        AbilityService::invalidate();
        $request->session()->flash('success', 'Ability создана.');
        return redirect('/admin/abilities', 302);
    }

    /** Форма редактирования (та же вьюха, вкладка edit) */
    public function edit(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }
        $id = (int)$id;
        if ($id <= 0) {
            return $this->renderIndexWithMessage($request, 'Некорректный идентификатор ability.');
        }

        $rows = DB::select("
            SELECT id, name, label, description, min_role
            FROM abilities
            WHERE id = ?
        ", [$id]);
        $ability = $rows[0] ?? null;
        if (!$ability) {
            return $this->renderIndexWithMessage($request, 'Ability не найдена.');
        }

        // ⛔ Блокируем доступ к форме правки «выше потолка»
        $myRole = $this->currentRoleId();
        if ((int)$ability['min_role'] > $myRole) {
            $msg = "Недостаточно прав: нельзя редактировать ability для роли выше вашей ".
                   "(min_role={$ability['min_role']}, ваш role_id={$myRole}).";
            return $this->renderIndexWithMessage($request, $msg);
        }

        $abilities   = DB::select("
            SELECT id, name, label, description, min_role
            FROM abilities
            ORDER BY name ASC
        ");
        $rolesAll    = DB::select("SELECT id, name, label FROM roles ORDER BY id ASC");
        $roleLabels  = $this->roleLabelsMap($rolesAll);
        $rolesSelect = $this->filterRolesByCeiling($rolesAll, $myRole);

        return response()->view('admin.abilities', [
            'groups'  => $this->groupByTopPrefix($abilities, $roleLabels),
            'roles'   => $rolesSelect,
            'ability' => $ability,
            'perm'    => ['can_manage' => true],
            'tab'     => 'edit',
            'flash'   => [
                'error'   => $request->session()->get('error'),
                'success' => $request->session()->get('success'),
            ],
        ]);
    }

    /** Обновление записи */
    public function update(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }
        $id = (int)$id;
        if ($id <= 0) {
            $request->session()->flash('error', 'Некорректный идентификатор ability.');
            return redirect('/admin/abilities', 302);
        }

        // 1) Текущая min_role из БД — нельзя менять чужую «выше потолка»
        $row = DB::select("SELECT min_role FROM abilities WHERE id = ?", [$id]);
        if (empty($row)) {
            $request->session()->flash('error', 'Ability не найдена.');
            return redirect('/admin/abilities', 302);
        }
        $currentMin = (int)($row[0]['min_role'] ?? 0);
        $myRole     = $this->currentRoleId();
        if ($currentMin > $myRole) {
            $request->session()->flash(
                'error',
                "Недостаточно прав: нельзя изменять ability для роли выше вашей ".
                "(текущая min_role={$currentMin}, ваш role_id={$myRole})."
            );
            return redirect('/admin/abilities/'.$id.'/edit', 302);
        }

        // 2) Новые данные
        $data = [
            'name'        => trim((string)$request->input('name')),
            'label'       => trim((string)$request->input('label')),
            'description' => trim((string)$request->input('description')),
            'min_role'    => (int)$request->input('min_role'),
        ];

        $v = Validator::make($data, [
            'name'        => ['required','string','min:3','max:100'],
            'label'       => ['nullable','string','max:100'],
            'description' => ['nullable','string','max:10000'],
            'min_role'    => ['required','integer','min:-128','max:127'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $list) { $flat = array_merge($flat, $list); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities/'.$id.'/edit', 302);
        }

        // 3) Запрещаем повышать min_role выше своей
        if ($data['min_role'] > $myRole) {
            $request->session()->flash(
                'error',
                "Недостаточно прав: нельзя сохранять ability для роли выше вашей ".
                "(min_role={$data['min_role']}, ваш role_id={$myRole})."
            );
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities/'.$id.'/edit', 302);
        }

        // 4) Уникальность name
        $exists = (int)DB::scalar(
            "SELECT COUNT(*) FROM abilities WHERE name = ? AND id <> ?",
            [$data['name'], $id]
        );
        if ($exists) {
            $request->session()->flash('error', 'Ability с таким name уже существует.');
            $request->session()->flash('content', $data);
            return redirect('/admin/abilities/'.$id.'/edit', 302);
        }

        $now = time();
        DB::statement(
            "UPDATE abilities
               SET name = ?, label = ?, description = ?, min_role = ?, updated_at = ?
             WHERE id = ?",
            [$data['name'], $data['label'], $data['description'], $data['min_role'], $now, $id]
        );

        AbilityService::invalidate();
        $request->session()->flash('success', 'Ability обновлена.');
        return redirect('/admin/abilities', 302);
    }

    /** Удаление записи */
    public function delete(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }
        $id = (int)$id;
        if ($id <= 0) {
            return $this->renderIndexWithMessage($request, 'Некорректный идентификатор ability.');
        }

        $row = DB::select("SELECT min_role FROM abilities WHERE id = ?", [$id]);
        if (empty($row)) {
            return $this->renderIndexWithMessage($request, 'Ability не найдена.');
        }

        $minRole = (int)($row[0]['min_role'] ?? 0);
        $myRole  = $this->currentRoleId();

        if ($minRole > $myRole) {
            $msg = "Недостаточно прав: нельзя удалять ability для роли выше вашей ".
                   "(min_role={$minRole}, ваш role_id={$myRole}).";
            return $this->renderIndexWithMessage($request, $msg);
        }

        DB::statement("DELETE FROM abilities WHERE id = ?", [$id]);

        AbilityService::invalidate();
        $request->session()->flash('success', 'Ability удалена.');
        return redirect('/admin/abilities', 302);
    }

    /** Ререндер списка с сообщением (без редиректа) */
    protected function renderIndexWithMessage(
        Request $request,
        ?string $error = null,
        ?string $success = null
    ): Response {
        if ($resp = $this->authorizeView()) { return $resp; }

        $abilities   = DB::select("
            SELECT id, name, label, description, min_role
            FROM abilities
            ORDER BY name ASC
        ");
        $rolesAll     = DB::select("SELECT id, name, label FROM roles ORDER BY id ASC");
        $roleLabels   = $this->roleLabelsMap($rolesAll);
        $rolesSelect  = $this->filterRolesByCeiling($rolesAll, $this->currentRoleId());
        $perm         = ['can_manage' => $this->adminPolicy->canManageAbilities($this->currentRoleId())];

        return response()->view('admin.abilities', [
            'groups' => $this->groupByTopPrefix($abilities, $roleLabels),
            'roles'  => $rolesSelect,
            'perm'   => $perm,
            'tab'    => 'index',
            'flash'  => [
                'success' => $success ?? $request->session()->get('success'),
                'error'   => $error   ?? $request->session()->get('error'),
            ],
        ]);
    }

    // ================= policy helpers =================

    protected function authorizeView(): ?Response
    {
        $u = Auth::user();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    protected function authorizeManage(): ?Response
    {
        $u = Auth::user();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canManageAbilities($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    // ================= helpers =================

    protected function currentRoleId(): int
    {
        $u = Auth::user();
        return (int)($u['role_id'] ?? 0);
    }

    protected function filterRolesByCeiling(array $roles, int $ceiling): array
    {
        $out = [];
        foreach ($roles as $r) {
            $rid = (int)($r['id'] ?? -1000);
            if ($rid <= $ceiling) { $out[] = $r; }
        }
        return $out;
    }

    /** Карта id роли -> подпись */
    protected function roleLabelsMap(array $roles): array
    {
        $map = [];
        foreach ($roles as $r) {
            $id = (int)($r['id'] ?? 0);
            $label = trim((string)($r['label'] ?? ''));
            $name  = trim((string)($r['name']  ?? ''));
            $map[$id] = $label !== '' ? $label : ($name !== '' ? $name : (string)$id);
        }
        return $map;
    }

    /**
     * Группировка по первому слову (до первой точки).
     * Выход:
     * [
     *   'admin' => ['title'=>'admin', 'count'=>N, 'items'=>[ {id,name,label,min_role,min_role_label}, ... ]],
     *   'forum' => [...],
     * ]
     */
    protected function groupByTopPrefix(array $abilities, array $roleLabels): array
    {
        $groups = [];
        foreach ($abilities as $ab) {
            $id   = (int)$ab['id'];
            $name = (string)$ab['name'];
            $label= (string)($ab['label'] ?? '');
            $mnr  = (int)($ab['min_role'] ?? 0);

            $top = (string)explode('.', $name, 2)[0];
            $top = $top !== '' ? strtolower($top) : '(other)';

            if (!isset($groups[$top])) {
                $groups[$top] = [
                    'title' => $top,
                    'count' => 0,
                    'items' => [],
                ];
            }

            $groups[$top]['items'][] = [
                'id'             => $id,
                'name'           => $name,
                'label'          => $label,
                'min_role'       => $mnr,
                'min_role_label' => $roleLabels[$mnr] ?? (string)$mnr,
            ];
            $groups[$top]['count']++;
        }

        // Сортировки: группы по названию; элементы внутри — по name
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($groups as &$g) {
            usort($g['items'], fn($a,$b) => strnatcasecmp($a['name'], $b['name']));
        }
        unset($g);

        return $groups;
    }
}
