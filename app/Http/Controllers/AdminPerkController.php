<?php
// v0.1.5
// AdminPerkController — админ CRUD перков. Добавлена централизованная авторизация через
// AdminVisibilityPolicy (просмотр — доступ в админку; управление — canManagePerks).
// FIX: добавлены use, приватное поле, конструктор, authorizeView()/authorizeManage() и их вызовы.

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use Faravel\Support\Facades\Auth;
use App\Services\Auth\AbilityService;
use App\Services\Auth\PerkService;
use App\Services\Auth\AdminVisibilityPolicy;

class AdminPerkController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Просмотр списка перков (для админов; Ability остаётся для payload/совместимости) */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorizeView()) { return $resp; }

        $user  = $this->currentUser();
        $perks = DB::select("
            SELECT id, `key`, label, description, min_group_id
            FROM perks
            ORDER BY `key` ASC
        ");

        return response()->view('admin.perks.index', [
            'perks' => $perks,
            'perm'  => $this->permPayload($user),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * Требует одновременно: admin.access И admin.perks.manage.
     * При отсутствии — мгновенно рендерим индекс с сообщением (без редиректа).
     */
    protected function ensureManageOrRenderIndex(Request $request, ?string $msg = null): ?Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $user = $this->currentUser();
        if (!$this->canManagePerks($user)) {
            $message = $msg ?? 'Недостаточно прав: управление Perks запрещено для вашей учётки.';
            return $this->renderIndexWithMessage($request, $message);
        }
        return null;
    }

    /** Форма создания */
    public function create(Request $request): Response
    {
        if ($resp = $this->ensureManageOrRenderIndex($request)) { return $resp; }

        return response()->view('admin.perks.form', [
            'mode'  => 'create',
            'perk'  => null,
            'perm'  => $this->permPayload($this->currentUser()),
            'flash' => [
                'error'   => $request->session()->get('error'),
                'content' => $request->session()->get('content'),
            ],
        ]);
    }

    /** Создание */
    public function store(Request $request): Response
    {
        if ($resp = $this->ensureManageOrRenderIndex($request)) { return $resp; }

        $data = [
            'key'          => trim((string)$request->input('key')),
            'label'        => trim((string)$request->input('label')),
            'description'  => trim((string)$request->input('description')),
            'min_group_id' => (int)$request->input('min_group_id'),
        ];

        $v = Validator::make($data, [
            'key'          => ['required','string','min:3','max:100'],
            'label'        => ['nullable','string','max:100'],
            'description'  => ['nullable','string','max:10000'],
            'min_group_id' => ['required','integer','min:0','max:127'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $errs) { $flat = array_merge($flat, $errs); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/new', 302);
        }

        // уникальность key
        $exists = (int) DB::scalar("SELECT COUNT(*) FROM perks WHERE `key` = ?", [$data['key']]);
        if ($exists) {
            $request->session()->flash('error', 'Perk с таким key уже существует.');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/new', 302);
        }

        $now = time();
        DB::insert(
            "INSERT INTO perks (`key`, label, description, min_group_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$data['key'], $data['label'], $data['description'], $data['min_group_id'], $now, $now]
        );

        PerkService::invalidate();
        $request->session()->flash('success', 'Perk создан.');
        return redirect('/admin/perks', 302);
    }

    /** Форма редактирования */
    public function edit(Request $request, int $id): Response
    {
        if ($resp = $this->ensureManageOrRenderIndex($request)) { return $resp; }

        $id = (int)$id;
        if ($id <= 0) {
            return $this->renderIndexWithMessage($request, 'Некорректный идентификатор перка.');
        }

        $rows = DB::select("
            SELECT id, `key`, label, description, min_group_id
            FROM perks
            WHERE id = ?
        ", [$id]);
        $perk = $rows[0] ?? null;
        if (!$perk) {
            return $this->renderIndexWithMessage($request, 'Perk не найден.');
        }

        return response()->view('admin.perks.form', [
            'mode'  => 'edit',
            'perk'  => $perk,
            'perm'  => $this->permPayload($this->currentUser()),
            'flash' => [
                'error'   => $request->session()->get('error'),
                'content' => $request->session()->get('content'),
            ],
        ]);
    }

    /** Обновление */
    public function update(Request $request, int $id): Response
    {
        if ($resp = $this->ensureManageOrRenderIndex($request)) { return $resp; }

        $id = (int)$id;
        if ($id <= 0) {
            $request->session()->flash('error', 'Некорректный идентификатор перка.');
            return redirect('/admin/perks', 302);
        }

        $data = [
            'key'          => trim((string)$request->input('key')),
            'label'        => trim((string)$request->input('label')),
            'description'  => trim((string)$request->input('description')),
            'min_group_id' => (int)$request->input('min_group_id'),
        ];

        $v = Validator::make($data, [
            'key'          => ['required','string','min:3','max:100'],
            'label'        => ['nullable','string','max:100'],
            'description'  => ['nullable','string','max:10000'],
            'min_group_id' => ['required','integer','min:0','max:127'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $errs) { $flat = array_merge($flat, $errs); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/'.$id.'/edit', 302);
        }

        // уникальность key (кроме текущей записи)
        $exists = (int) DB::scalar(
            "SELECT COUNT(*) FROM perks WHERE `key` = ? AND id <> ?",
            [$data['key'], $id]
        );
        if ($exists) {
            $request->session()->flash('error', 'Perk с таким key уже существует.');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/'.$id.'/edit', 302);
        }

        $now = time();
        DB::statement(
            "UPDATE perks
               SET `key` = ?, label = ?, description = ?, min_group_id = ?, updated_at = ?
             WHERE id = ?",
            [$data['key'], $data['label'], $data['description'], $data['min_group_id'], $now, $id]
        );

        PerkService::invalidate();
        $request->session()->flash('success', 'Perk обновлён.');
        return redirect('/admin/perks', 302);
    }

    /** Удаление */
    public function delete(Request $request, int $id): Response
    {
        if ($resp = $this->ensureManageOrRenderIndex($request)) { return $resp; }

        $id = (int)$id;
        if ($id <= 0) {
            return $this->renderIndexWithMessage($request, 'Некорректный идентификатор перка.');
        }

        // Проверим существование для корректного сообщения
        $exists = (int) DB::scalar("SELECT COUNT(*) FROM perks WHERE id = ?", [$id]);
        if (!$exists) {
            return $this->renderIndexWithMessage($request, 'Perk не найден.');
        }

        DB::statement("DELETE FROM perks WHERE id = ?", [$id]);
        PerkService::invalidate();
        $request->session()->flash('success', 'Perk удалён.');
        return redirect('/admin/perks', 302);
    }

    /** Мгновенный рендер индекса с сообщением (без редиректа) */
    protected function renderIndexWithMessage(
        Request $request,
        ?string $error = null,
        ?string $success = null
    ): Response {
        $perks = DB::select("
            SELECT id, `key`, label, description, min_group_id
            FROM perks
            ORDER BY `key` ASC
        ");

        return response()->view('admin.perks.index', [
            'perks' => $perks,
            'perm'  => $this->permPayload($this->currentUser()),
            'flash' => [
                'success' => $success ?? $request->session()->get('success'),
                'error'   => $error   ?? $request->session()->get('error'),
            ],
        ]);
    }

    // ================= policy helpers =================

    protected function authorizeView(): ?Response
    {
        $u = $this->currentUser();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    protected function authorizeManage(): ?Response
    {
        $u = $this->currentUser();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canManagePerks($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    // ================= legacy helpers (оставляем для совместимости UI) =================

    protected function currentUser(): ?array
    {
        return Auth::user() ?: null; // Faravel, судя по всему, возвращает массив
    }

    protected function canViewPerks(?array $user): bool
    {
        return AbilityService::has($user, 'admin.access')
            || AbilityService::has($user, 'admin.perks.manage');
    }

    protected function canManagePerks(?array $user): bool
    {
        return AbilityService::has($user, 'admin.access')
            && AbilityService::has($user, 'admin.perks.manage');
    }

    protected function permPayload(?array $user): array
    {
        return [
            'can_view'   => $this->canViewPerks($user),
            'can_manage' => $this->canManagePerks($user),
        ];
    }
}
