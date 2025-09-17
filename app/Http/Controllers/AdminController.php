<?php // v0.4.1
/* app/Http/Controllers/AdminController.php
Purpose: Панель админки: обзор и настройки (throttle). Тонкий контроллер.
FIX: Прокинут LayoutService: добавлен параметр 'layout' для строгого Blade, без
     глобального share(). Все админские view теперь получают $layout.
*/

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\Validator;
use App\Services\SettingsService;
use App\Services\Auth\AdminVisibilityPolicy;
use App\Services\Layout\LayoutService;

final class AdminController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        // Lazy DI-compatible initialization
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Главная админки (карточки формируются в Blade) */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.index', [
            'title'  => 'Обзор',
            'layout' => $layout,
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

        $layout = (new LayoutService())->build($request, [
            'title'      => 'Админка: Настройки',
            'nav_active' => 'admin',
        ])->toArray();

        return response()->view('admin.settings', $data + [
            'layout' => $layout,
        ]);
    }

    /** POST сохранение настроек троттлинга */
    public function settingsSave(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $raw = $request->only(['window_sec', 'get_max', 'post_max', 'session_max', 'exempt_paths']);

        $validator = Validator::make($raw, [
            'window_sec'  => 'required|int|min:1|max:3600',
            'get_max'     => 'required|int|min:1|max:10000',
            'post_max'    => 'required|int|min:1|max:10000',
            'session_max' => 'required|int|min:1|max:50000',
            'exempt_paths'=> 'string',
        ]);

        if ($validator->fails()) {
            $request->session()->put('error', 'Некорректные значения настроек.');
            $request->session()->flash('error', 'Некорректные значения настроек.');
            return redirect('/admin/settings');
        }

        SettingsService::set('throttle.window.sec',   (int)$raw['window_sec']);
        SettingsService::set('throttle.get.max',      (int)$raw['get_max']);
        SettingsService::set('throttle.post.max',     (int)$raw['post_max']);
        SettingsService::set('throttle.session.max',  (int)$raw['session_max']);
        SettingsService::set('throttle.exempt.paths', (string)($raw['exempt_paths'] ?? ''));

        $request->session()->flash('success', 'Настройки сохранены.');
        return redirect('/admin/settings');
    }

    /** Простая проверка доступа в админку (поверх middleware AdminOnly) */
    protected function authorize(): ?Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/login');
        }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }
}
