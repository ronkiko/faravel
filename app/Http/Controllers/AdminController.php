<?php // v0.4.2
/* app/Http/Controllers/AdminController.php
Purpose: Панель админки: обзор и настройки троттлинга. Тонкий контроллер.
FIX: На страницу настроек добавлены поля троттлинга постинга:
     - throttle.post.cooldown.guest
     - throttle.post.cooldown.default
     - throttle.post.cooldown.groups (строки "group_id=seconds").
     Методы settings() и settingsSave() расширены; Blade получает новые поля.
*/

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\Validator;
use Faravel\Support\Facades\DB;
use App\Services\SettingsService;
use App\Services\Auth\AdminVisibilityPolicy;
use App\Services\Layout\LayoutService;

final class AdminController
{
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Обзор (оставляем как есть, минимальная заглушка) */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }
        $layout = (new LayoutService())->build($request, [
            'title' => 'Админка: Обзор',
        ])->toArray();

        return response()->view('admin.index', [
            'title'  => 'Обзор',
            'layout' => $layout,
        ]);
    }

    /**
     * Страница настроек троттлинга и постинга.
     *
     * @param Request $request
     * @return Response
     */
    public function settings(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        // Основные параметры уже существующего троттлинга
        $data = [
            'title'        => 'Настройки троттлинга',
            'window_sec'   => SettingsService::getInt('throttle.window.sec',   60, 1, 3600),
            'get_max'      => SettingsService::getInt('throttle.get.max',     120, 1, 10000),
            'post_max'     => SettingsService::getInt('throttle.post.max',    15,  1, 10000),
            'session_max'  => SettingsService::getInt('throttle.session.max', 300, 1, 50000),
            'exempt_paths' => (string) SettingsService::get('throttle.exempt.paths', ''),
        ];

        // Новые параметры: пауза между постами
        $data += [
            'post_cd_guest'   => (int) SettingsService::get('throttle.post.cooldown.guest',   -1),
            'post_cd_default' => (int) SettingsService::get('throttle.post.cooldown.default', 60),
            'post_cd_groups'  => (string) SettingsService::get('throttle.post.cooldown.groups', ''),
        ];

        $layout = (new LayoutService())->build($request, [
            'title' => 'Админка: Настройки',
        ])->toArray();

        return response()->view('admin.settings', $data + [
            'layout' => $layout,
        ]);
    }

    /**
     * POST сохранение настроек троттлинга и постинга.
     *
     * @param Request $request
     * @return Response
     */
    public function settingsSave(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $raw = [
            'window_sec'   => (string)$request->input('window_sec'),
            'get_max'      => (string)$request->input('get_max'),
            'post_max'     => (string)$request->input('post_max'),
            'session_max'  => (string)$request->input('session_max'),
            'exempt_paths' => (string)$request->input('exempt_paths'),

            // Новые поля
            'post_cd_guest'   => (string)$request->input('post_cd_guest', '-1'),
            'post_cd_default' => (string)$request->input('post_cd_default', '60'),
            'post_cd_groups'  => (string)$request->input('post_cd_groups', ''),
        ];

        $v = Validator::make($raw, [
            'window_sec'      => ['required','integer','min:1','max:3600'],
            'get_max'         => ['required','integer','min:1','max:10000'],
            'post_max'        => ['required','integer','min:1','max:10000'],
            'session_max'     => ['required','integer','min:1','max:50000'],
            'exempt_paths'    => ['nullable','string','max:5000'],
            'post_cd_guest'   => ['required','integer','min:-1','max:86400'],
            'post_cd_default' => ['required','integer','min:0','max:86400'],
            'post_cd_groups'  => ['nullable','string','max:10000'],
        ]);
        if ($v->fails()) {
            $request->session()->flash('error', 'Некорректные значения.');
            return redirect('/admin/settings');
        }

        // Сохранение базовых ключей
        SettingsService::set('throttle.window.sec',   (int)$raw['window_sec']);
        SettingsService::set('throttle.get.max',      (int)$raw['get_max']);
        SettingsService::set('throttle.post.max',     (int)$raw['post_max']);
        SettingsService::set('throttle.session.max',  (int)$raw['session_max']);
        SettingsService::set('throttle.exempt.paths', (string)$raw['exempt_paths']);

        // Сохранение новых ключей
        SettingsService::set('throttle.post.cooldown.guest',   (int)$raw['post_cd_guest']);
        SettingsService::set('throttle.post.cooldown.default', (int)$raw['post_cd_default']);

        // Храним весь блок переопределений одной строкой для простоты
        // Формат: строки вида "group_id=seconds"
        $groupsText = trim($raw['post_cd_groups']);
        SettingsService::set('throttle.post.cooldown.groups', $groupsText);

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
        if (!(new AdminVisibilityPolicy())->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }
}
