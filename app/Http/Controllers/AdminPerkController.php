<?php // v0.4.2
/* app/Http/Controllers/AdminPerkController.php
Purpose: Единый контроллер админки «перков», рендерящий одну страницу perks.blade
         для режимов списка, создания и правки. Тонкий контроллер: данные берутся
         из БД и сервисов, Blade — только вывод.
FIX: renderPage() теперь возвращает Response: response(view(...)->render()).
     В edit() форма использует метод PUT для единообразия. Убран лишний каст.
*/

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use Faravel\Support\Facades\Auth;
use App\Services\Auth\AbilityService;
use App\Services\Auth\AdminVisibilityPolicy;
use App\Services\Layout\LayoutService;

final class AdminPerkController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /**
     * Список + форма создания.
     *
     * @param Request $request
     * @return Response
     *
     * Preconditions:
     * - Пользователь авторизован и имеет доступ в админку.
     * Side effects: читает сессию для флеш-сообщений.
     */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorizeView()) { return $resp; }

        return $this->renderPage($request, 'create', [
            'form' => [
                'id'            => '',
                'key'           => (string)($request->session()->get('content.key') ?? ''),
                'label'         => (string)($request->session()->get('content.label') ?? ''),
                'description'   => (string)($request->session()->get('content.description') ?? ''),
                'min_group_id'  => (string)($request->session()->get('content.min_group_id') ?? ''),
            ],
            'form_action'       => '/admin/perks',
            'form_http_method'  => '',
        ]);
    }

    /**
     * Показ формы создания (совместимость с маршрутами).
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        return $this->index($request);
    }

    /**
     * Сохранение новой записи и возврат к списку.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $data = [
            'key'           => trim((string)$request->input('key')),
            'label'         => trim((string)$request->input('label')),
            'description'   => trim((string)$request->input('description')),
            'min_group_id'  => (int)$request->input('min_group_id'),
        ];

        $v = Validator::make($data, [
            'key'           => ['required','string','min:2','max:100'],
            'label'         => ['nullable','string','max:100'],
            'description'   => ['nullable','string','max:10000'],
            'min_group_id'  => ['required','integer','min:1','max:1000000'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $list) { $flat = array_merge($flat, $list); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks', 302);
        }

        // Уникальность key
        $exists = (int)DB::scalar(
            "SELECT COUNT(*) FROM perks WHERE `key` = ?",
            [$data['key']]
        );
        if ($exists > 0) {
            $request->session()->flash('error', "Перк с ключом '{$data['key']}' уже существует.");
            $request->session()->flash('content', $data);
            return redirect('/admin/perks', 302);
        }

        DB::insert(
            "INSERT INTO perks (`key`,`label`,`description`,`min_group_id`) VALUES (?,?,?,?)",
            [$data['key'], $data['label'], $data['description'], $data['min_group_id']]
        );

        $request->session()->flash('success', 'Перк создан.');
        return redirect('/admin/perks', 302);
    }

    /**
     * Форма правки записи.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function edit(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }
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
            return $this->renderIndexWithMessage($request, 'Перк не найден.');
        }

        return $this->renderPage($request, 'edit', [
            'form' => [
                'id'            => (string)$perk['id'],
                'key'           => (string)$perk['key'],
                'label'         => (string)($perk['label'] ?? ''),
                'description'   => (string)($perk['description'] ?? ''),
                'min_group_id'  => (string)($perk['min_group_id'] ?? ''),
            ],
            'form_action'      => '/admin/perks/'.$id,
            'form_http_method' => 'PUT',
        ]);
    }

    /**
     * Обновление записи.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $id = (int)$id;
        if ($id <= 0) {
            return $this->renderIndexWithMessage($request, 'Некорректный идентификатор перка.');
        }

        $data = [
            'key'           => trim((string)$request->input('key')),
            'label'         => trim((string)$request->input('label')),
            'description'   => trim((string)$request->input('description')),
            'min_group_id'  => (int)$request->input('min_group_id'),
        ];

        $v = Validator::make($data, [
            'key'           => ['required','string','min:2','max:100'],
            'label'         => ['nullable','string','max:100'],
            'description'   => ['nullable','string','max:10000'],
            'min_group_id'  => ['required','integer','min:1','max:1000000'],
        ]);

        if ($v->fails()) {
            $flat = [];
            foreach ($v->errors() as $list) { $flat = array_merge($flat, $list); }
            $request->session()->flash('error', $flat[0] ?? 'Validation error');
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/'.$id.'/edit', 302);
        }

        // Уникальность key (кроме текущей записи)
        $exists = (int)DB::scalar(
            "SELECT COUNT(*) FROM perks WHERE `key` = ? AND id <> ?",
            [$data['key'], $id]
        );
        if ($exists > 0) {
            $request->session()->flash('error', "Перк с ключом '{$data['key']}' уже существует.");
            $request->session()->flash('content', $data);
            return redirect('/admin/perks/'.$id.'/edit', 302);
        }

        DB::update(
            "UPDATE perks SET `key`=?, label=?, description=?, min_group_id=? WHERE id=?",
            [$data['key'], $data['label'], $data['description'], $data['min_group_id'], $id]
        );

        $request->session()->flash('success', 'Перк обновлён.');
        return redirect('/admin/perks/'.$id.'/edit', 302);
    }

    /**
     * Удаление записи и возврат к списку.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function delete(Request $request, int $id): Response
    {
        if ($resp = $this->authorizeManage()) { return $resp; }

        $id = (int)$id;
        if ($id > 0) {
            DB::delete("DELETE FROM perks WHERE id = ?", [$id]);
            $request->session()->flash('success', 'Перк удалён.');
        } else {
            $request->session()->flash('error', 'Некорректный идентификатор перка.');
        }

        return redirect('/admin/perks', 302);
    }

    /**
     * Универсальный рендер одной страницы (список + форма).
     *
     * @param Request             $request
     * @param 'create'|'edit'     $mode
     * @param array<string,mixed> $over
     * @return Response
     *
     * @example GET /admin/perks → renderPage('create', …)
     * @example GET /admin/perks/5/edit → renderPage('edit', …)
     */
    private function renderPage(Request $request, string $mode, array $over): Response
    {
        $layout = (new LayoutService())->build($request, ['title' => 'Perks']);

        $perks = DB::select("
            SELECT id, `key`, label, description, min_group_id,
                   CONCAT('/admin/perks/', id, '/edit')   AS edit_url,
                   CONCAT('/admin/perks/', id, '/delete') AS delete_action
            FROM perks
            ORDER BY `key` ASC
        ");
        $groups = DB::select("SELECT id, name FROM groups ORDER BY id ASC");

        $data = [
            'layout'        => $layout,
            'has_error'     => (bool)$request->session()->get('error'),
            'flash_error'   => (string)($request->session()->get('error') ?? ''),
            'has_success'   => (bool)$request->session()->get('success'),
            'flash_success' => (string)($request->session()->get('success') ?? ''),
            'perm'          => ['can_manage' => $this->canManagePerks(Auth::user())],
            'perks'         => $perks,
            'groups'        => $groups,
            'form_mode'     => $mode,
            'form_action'   => (string)($over['form_action'] ?? '/admin/perks'),
            'form_http_method' => (string)($over['form_http_method'] ?? ''),
            'form'          => (array)($over['form'] ?? [
                'id'            => '',
                'key'           => '',
                'label'         => '',
                'description'   => '',
                'min_group_id'  => '',
            ]),
        ];

        // Преобразуем View → Response
        $v = view('admin.perks', $data);
        return response($v->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Рендер списка с сообщением об ошибке.
     *
     * @param Request $request
     * @param string  $message
     * @return Response
     */
    private function renderIndexWithMessage(Request $request, string $message): Response
    {
        $request->session()->flash('error', $message);
        return redirect('/admin/perks', 302);
    }

    /**
     * Доступ в админку.
     *
     * @return Response|null 403/redirect или null, если можно продолжать.
     */
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

    /**
     * Право управлять перками.
     *
     * @return Response|null
     */
    protected function authorizeManage(): ?Response
    {
        $u = Auth::user();
        if (!$u) { return redirect('/login'); }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canManagePerks($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    /**
     * Быстрые проверки прав в шаблоне.
     *
     * @param array<string,mixed>|null $user
     * @return bool
     */
    protected function canManagePerks(?array $user): bool
    {
        return AbilityService::has($user, 'admin.access')
            && AbilityService::has($user, 'admin.perks.manage');
    }
}
