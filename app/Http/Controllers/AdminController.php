<?php
// v0.3.117
// AdminController — главная админки и настройки троттлинга.
// FIX: 1) Список карточек убран из контроллера; теперь формируется ТОЛЬКО в Blade (admin.index).
//      2) Авторизация приведена к централизованной политике: AdminVisibilityPolicy::canAccessAdmin().
// Бизнес-логика не менялась, маршруты не менялись, представления совместимы.

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\Validator;
use App\Services\SettingsService;
use App\Services\Auth\AdminVisibilityPolicy;

class AdminController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        // Ленивый резолв — без DI-контейнера.
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Главная админки: карточки разделов (карточки теперь формируются в Blade) */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        // Ничего не передаём специально: список карточек — в admin.index.blade.php
        return response()->view('admin.index', [
            'title' => 'Обзор',
        ]);
    }

    /** Страница настроек троттлинга */
    public function settings(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $data = [
            'title'        => 'Настройки троттлинга',
            'window_sec'   => SettingsService::getInt('throttle.window.sec',   60, 1, 3600),
            'get_max'      => SettingsService::getInt('throttle.get.max',     120, 1, 10000),
            'post_max'     => SettingsService::getInt('throttle.post.max',    15,  1, 10000),
            'session_max'  => SettingsService::getInt('throttle.session.max', 300, 1, 50000),
            'exempt_paths' => (string) SettingsService::get('throttle.exempt.paths', ''),
        ];

        return response()->view('admin.settings', $data);
    }

    public function settingsSave(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $raw = $request->only(['window_sec', 'get_max', 'post_max', 'session_max', 'exempt_paths']);

        $validator = Validator::make($raw, [
            'window_sec'   => 'required|numeric|min:1|max:3600',
            'get_max'      => 'required|numeric|min:1|max:10000',
            'post_max'     => 'required|numeric|min:1|max:10000',
            'session_max'  => 'required|numeric|min:1|max:50000',
            'exempt_paths' => 'string|max:2000',
        ]);

        if ($validator->fails()) {
            $flat = [];
            foreach ($validator->errors() as $list) {
                foreach ($list as $msg) {
                    $flat[] = $msg;
                }
            }
            $request->session()->flash('error', $flat[0] ?? 'Ошибка валидации');
            $request->session()->flash('error_list', $flat);
            return redirect('/admin/settings');
        }

        // Сохраняем
        SettingsService::set('throttle.window.sec',   (int)$raw['window_sec']);
        SettingsService::set('throttle.get.max',      (int)$raw['get_max']);
        SettingsService::set('throttle.post.max',     (int)$raw['post_max']);
        SettingsService::set('throttle.session.max',  (int)$raw['session_max']);

        $csv  = trim((string)$raw['exempt_paths']);
        $norm = [];
        if ($csv !== '') {
            foreach (explode(',', $csv) as $p) {
                $p = rtrim(trim($p), '/');
                $norm[] = ($p === '') ? '/' : $p;
            }
        }
        SettingsService::set('throttle.exempt.paths', implode(',', $norm));

        $request->session()->flash('success', 'Настройки троттлинга сохранены.');
        return redirect('/admin/settings');
    }

    /** Централизованная авторизация на вход в админку */
    protected function authorize(): ?Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/login');
        }
        $roleId = (int)($u['role_id'] ?? 0);

        // Используем политику доступа к админке
        if (!$this->adminPolicy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }
}
