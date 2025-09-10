<?php // v0.3.22

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;

class ProfileController
{
    public function show(Request $request): Response
    {
        $user = Auth::user();
        if (!$user || !isset($user['id'])) {
            return redirect('/login');
        }

        $sess = $request->session();
        $uid  = (string)$user['id'];

        // Ленивая гидратация подписи роли/группы в сессию
        if ((!isset($user['role_label']) || $user['role_label'] === '') && isset($user['role_id'])) {
            $r = DB::table('roles')->select('label','name')->where('id', '=', (int)$user['role_id'])->first();
            $user['role_label'] = trim((string)($r['label'] ?? $r['name'] ?? 'User'));
            $this->putAuthUser($sess, $user);
        }
        if ((!isset($user['group_name']) || $user['group_name'] === '') && isset($user['group_id'])) {
            $g = DB::table('groups')->select('name')->where('id', '=', (int)$user['group_id'])->first();
            $user['group_name'] = (string)($g['name'] ?? '—');
            $this->putAuthUser($sess, $user);
        }

        $roleLabel = (string)($user['role_label'] ?? 'User');
        $groupName = (string)($user['group_name'] ?? '—');

        // Статы — кэш в сессии
        $stats = $sess->get('_profile.stats');
        if (!is_array($stats) || ($stats['user_id'] ?? null) !== $uid) {
            $topics = DB::table('topics')->select('id')->where('user_id', '=', $uid)->get();
            $posts  = DB::table('posts')->select('id')->where('user_id', '=', $uid)->get();
            $stats = [
                'user_id' => $uid,
                'topics'  => is_array($topics) ? count($topics) : 0,
                'posts'   => is_array($posts)  ? count($posts)  : 0,
            ];
            $sess->put('_profile.stats', $stats);
        }

        $lang     = isset($user['language_id']) ? (int)$user['language_id'] : null;
        $isActive = array_key_exists('is_active', $user) ? (bool)$user['is_active'] : true;

        // Стаж на форуме из users.registered (unix seconds или индекс дня)
        $regIndex = 0; $joinedDate = null; $registeredDays = 0;
        if (isset($user['registered'])) {
            $regRaw = (int)$user['registered'];
            if ($regRaw > 1000000000) {
                $regIndex       = (int) floor($regRaw / 86400);
                $registeredDays = (int) floor((time() - $regRaw) / 86400);
                $joinedDate     = date('Y-m-d', $regRaw);
            } else {
                $regIndex       = max(0, $regRaw);
                $todayIndex     = (int) floor(time() / 86400);
                $registeredDays = max(0, $todayIndex - $regIndex);
                $joinedDate     = date('Y-m-d', $regIndex * 86400);
            }
        } else {
            $regIndex       = (int)($user['reg_day'] ?? $user['created_day'] ?? $user['created_at_day'] ?? 0);
            $registeredDays = $this->calcDays($regIndex);
            $joinedDate     = $this->calcDate($regIndex);
        }

        // аватар-URL решает вьюха (может быть пусто)
        $avatarRaw = (string)($user['avatar'] ?? $user['avatar_url'] ?? '');

        return response()->view('profile', [
            'user' => $user,
            'profile' => [
                'avatar'         => $avatarRaw,
                'roleId'         => (int)($user['role'] ?? $user['role_id'] ?? 0),
                'roleLabel'      => $roleLabel,
                'groupName'      => $groupName,
                'isActive'       => $isActive,
                'regDayIndex'    => $regIndex,
                'registeredDays' => $registeredDays,
                'joinedDate'     => $joinedDate,
                'stats'          => [
                    'topics'      => (int)($stats['topics'] ?? 0),
                    'posts'       => (int)($stats['posts']  ?? 0),
                    'reputation'  => (int)($user['reputation'] ?? 0),
                    'likes'       => (int)($user['reputation'] ?? 0),
                ],
                'language_id'    => $lang,
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $current   = Auth::user() ?? [];
        $currentId = (string)($current['id'] ?? '');
        $targetId  = (string)($request->input('user_id') ?? $request->input('id') ?? $currentId);
        $canAdmin  = can('admin.*');
        $isSelf    = ($targetId === $currentId);
        $forbidden = (!$isSelf && !$canAdmin);

        $target = DB::table('users')->where('id', '=', $targetId)->first();
        if (!$target) {
            $request->session()->flash('error', 'Пользователь не найден.');
            return redirect('/profile');
        }

        $languages = DB::table('languages')->select('id', 'code', 'name')->orderBy('sort', 'ASC')->get();
        $roles     = DB::table('roles')->select('id', 'label', 'name')->orderBy('id', 'ASC')->get();
        $groups    = DB::table('groups')->select('id', 'name', 'reputation')->orderBy('id', 'ASC')->get();

        [$registeredDays, $registeredDate] = $this->calcFromRegistered($target);
        $lastUpdatedDays = $this->calcDaysFromTimestamp($target['last_updated'] ?? null);
        $lastPostHuman   = $this->humanFromTimestamp($target['last_post'] ?? null);

        return response()->view('profile_edit', [
            'user'             => $current,
            'target'           => $target,
            'languages'        => $languages,
            'roles'            => $roles,
            'groups'           => $groups,
            'isSelf'           => $isSelf,
            'canAdmin'         => $canAdmin,
            'forbidden'        => $forbidden,
            'registeredDays'   => $registeredDays,
            'registeredDate'   => $registeredDate,
            'lastUpdatedDays'  => $lastUpdatedDays,
            'lastPostHuman'    => $lastPostHuman,
        ]);
    }

    public function save(Request $request): Response
    {
        $current   = Auth::user() ?? [];
        $currentId = (string)($current['id'] ?? '');
        $targetId  = (string)($request->input('user_id') ?? '');
        if ($targetId === '') { $targetId = $currentId; }

        $canAdmin = can('admin.*');
        $isSelf   = ($targetId === $currentId);

        if (!$isSelf && !$canAdmin) {
            $request->session()->flash('error', 'Недостаточно прав для редактирования этого профиля.');
            return redirect('/profile');
        }

        $in = $request->all();
        $update = [];

        if (array_key_exists('username', $in) && ($isSelf || $canAdmin)) {
            $newUsername = trim((string)$in['username']);
            if ($newUsername !== '') $update['username'] = $newUsername;
        }

        if (array_key_exists('language_id', $in) && ($isSelf || $canAdmin)) {
            $languageId = (int)$in['language_id'];
            if ($languageId > 0) {
                $lang = DB::table('languages')->where('id', '=', $languageId)->first();
                if (!$lang) {
                    $request->session()->flash('error', 'Выбран некорректный язык.');
                    return redirect('/profile');
                }
                $update['language_id'] = $languageId;
                $code = strtolower((string)($lang['code'] ?? ''));
                if ($code !== '') { $request->session()->put('_locale', $code); }
            }
        }

        // Новое: avatar_shape → users.settings (JSON)
        if (array_key_exists('avatar_shape', $in) && ($isSelf || $canAdmin)) {
            $shape = (string)$in['avatar_shape'];
            $allowed = ['square','circle','star'];
            if (!in_array($shape, $allowed, true)) { $shape = 'square'; }

            $row = DB::table('users')->select('settings')->where('id', '=', $targetId)->first() ?: [];
            $settings = [];
            if (isset($row['settings']) && is_string($row['settings']) && $row['settings'] !== '') {
                $decoded = json_decode($row['settings'], true);
                if (is_array($decoded)) $settings = $decoded;
            }
            $settings['avatar_shape'] = $shape;
            $update['settings']       = json_encode($settings, JSON_UNESCAPED_UNICODE);
            $update['_settings_arr']  = $settings; // для сессии
        }

        if ($canAdmin) {
            if (array_key_exists('reputation', $in)) {
                $rep = (int)$in['reputation']; if ($rep < 0) $rep = 0;
                $update['reputation'] = $rep;
            }
            if (array_key_exists('group_id', $in)) {
                $gid = (int)$in['group_id'];
                if ($gid > 0) {
                    $grp = DB::table('groups')->where('id', '=', $gid)->first();
                    if (!$grp) {
                        $request->session()->flash('error', 'Выбрана некорректная группа.');
                        return redirect('/profile');
                    }
                    $update['group_id']  = $gid;
                    $update['_group_nm'] = (string)($grp['name'] ?? '—');
                }
            }
            if (array_key_exists('role_id', $in)) {
                $rid = (int)$in['role_id'];
                $role = DB::table('roles')->where('id', '=', $rid)->first();
                if (!$role) {
                    $request->session()->flash('error', 'Выбрана некорректная роль.');
                    return redirect('/profile');
                }
                $update['role_id']   = $rid;
                $update['_role_lbl'] = trim((string)($role['label'] ?? $role['name'] ?? 'User'));
            }
        }

        if (empty(array_filter($update, fn($v, $k)=>$k[0] !== '_' && $v !== null, ARRAY_FILTER_USE_BOTH))) {
            $request->session()->flash('info', 'Нет изменений для сохранения.');
            return redirect('/profile');
        }

        try {
            DB::table('users')->where('id', '=', $targetId)
                ->update(array_filter($update, fn($k)=>$k[0] !== '_', ARRAY_FILTER_USE_KEY));
        } catch (\Throwable $e) {
            $request->session()->flash('error', 'Не удалось сохранить изменения: ' . $e->getMessage());
            return redirect('/profile');
        }

        // Обновляем сессию
        if ($isSelf) {
            $sessUser = Auth::user() ?? [];
            foreach (['username','language_id','reputation','group_id','role_id','settings'] as $k) {
                if (array_key_exists($k, $update)) $sessUser[$k] = $update[$k];
            }
            if (isset($update['_group_nm'])) $sessUser['group_name'] = $update['_group_nm'];
            if (isset($update['_role_lbl'])) $sessUser['role_label'] = $update['_role_lbl'];
            if (isset($update['_settings_arr'])) $sessUser['_settings_arr'] = $update['_settings_arr'];

            $this->putAuthUser($request->session(), $sessUser);
        }

        $request->session()->flash('success', 'Профиль обновлён.');
        return redirect('/profile');
    }

    private function putAuthUser($session, array $user): void
    {
        try {
            $session->put('auth.user', $user);
            $session->put('authUser',  $user);
            $session->put('user',      $user);
        } catch (\Throwable $e) {}
        try {
            if (is_callable([Auth::class, 'setUser'])) {
                Auth::setUser($user);
            }
        } catch (\Throwable $e) {}
    }

    private function calcFromRegistered(array $u): array
    {
        $reg = $u['registered'] ?? null;
        if ($reg === null) return [0, null];

        $reg = (int)$reg;
        if ($reg > 1000000000) {
            $days = (int) floor((time() - $reg) / 86400);
            $date = date('Y-m-d', $reg);
            return [max(0, $days), $date];
        } else {
            $date = date('Y-m-d', $reg * 86400);
            $todayIndex = (int) floor(time() / 86400);
            $days = max(0, $todayIndex - max(0, $reg));
            return [$days, $date];
        }
    }
    private function calcDays(int $regIndex): int
    {
        $todayIndex = (int) floor(time() / 86400);
        $regIndex   = max(0, $regIndex);
        return max(0, $todayIndex - $regIndex);
    }
    private function calcDate(int $regIndex): ?string
    {
        if ($regIndex <= 0) return null;
        return date('Y-m-d', $regIndex * 86400);
    }
    private function calcDaysFromTimestamp($ts): ?int
    {
        if (!$ts) return null;
        $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
        if (!$t) return null;
        return (int) floor((time() - $t) / 86400);
    }
    private function humanFromTimestamp($ts): string
    {
        if (!$ts) return '—';
        $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
        if (!$t) return '—';
        return date('Y-m-d H:i', $t);
    }
}
