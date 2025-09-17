<?php // v0.4.5
/* app/Http/Controllers/AuthController.php
Purpose: Контроллер аутентификации (страницы и обработчики). В GET-методах
         используем LayoutService::build() для унификации лайаута.
FIX: Шаг 1 (MVC): контроллер больше не кладёт «толстого» пользователя в сессию и
     не передаёт массив в сервис. В login()/register() передаём только ID:
     Auth::login((string)$user['id']). Удалены приватные дублирующие методы
     hydrateUserForSession() и putAuthUser().
*/
namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;
use App\Services\Layout\LayoutService;
use App\Http\ViewModels\Auth\LoginPageVM;
use App\Http\ViewModels\Auth\RegisterPageVM;
use PDOException;

class AuthController
{
    /**
     * Show login form (strict Blade + unified layout builder).
     *
     * @param Request $request
     * @return Response
     */
    public function showLoginForm(Request $request): Response
    {
        $s        = $request->session();
        $old      = $s->get('_old_input') ?: [];
        $username = is_array($old) ? (string)($old['username'] ?? '') : '';

        $pageVM = LoginPageVM::fromArray([
            'title'            => 'Вход',
            'csrf'             => csrf_token(),
            'action'           => '/login',
            'prefill_username' => $username,
        ]);

        $layoutService = new LayoutService();
        $layoutVM = $layoutService->build($request, [
            'title'      => 'Вход',
            'nav_active' => 'login',
        ]);

        $flash = [
            'error'   => $s->get('error_list') ?: ($s->get('error') ? [(string)$s->get('error')] : []),
            'success' => [],
        ];

        return response()->view('auth.login', [
            'vm'     => $pageVM->toArray(),
            'layout' => $layoutVM->toArray(),
            'flash'  => $flash,
        ]);
    }

    /**
     * POST /login — validate credentials and sign in.
     *
     * Controller stays thin: delegates auth to service with user ID only.
     *
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        $data = $request->only(['username', 'password']);
        $username = (string)($data['username'] ?? '');
        $password = (string)($data['password'] ?? '');

        $request->session()->put('_old_input', ['username' => $username]);
        $request->session()->put('username', $username);

        $validator = Validator::make(
            ['username' => $username, 'password' => $password],
            ['username' => 'required|string|min:3|max:255', 'password' => 'required']
        );

        $errors = [];
        if ($validator->fails()) {
            foreach ($validator->errors() as $fieldErrors) {
                foreach ($fieldErrors as $msg) { $errors[] = $msg; }
            }
        }

        if ($password !== '') {
            $len = strlen($password);
            if ($len < 3)   { $errors[] = 'Пароль должен быть не короче 3 символов.'; }
            if ($len > 255) { $errors[] = 'Пароль не должен превышать 255 символов.'; }
        }

        if (!empty($errors)) {
            $s = $request->session();
            $s->put('error', $errors[0]);     $s->flash('error', $errors[0]);
            $s->put('error_list', $errors);   $s->flash('error_list', $errors);
            return redirect('/login', 302);
        }

        // Fetch user and cast to array immediately for uniform access.
        $userRow = DB::table('users')->where('username', '=', $username)->first();
        /** @var array<string,mixed>|null $user */
        $user = $userRow ? (array)$userRow : null;

        $ok = false;
        if ($user) {
            $stored = (string)($user['password'] ?? '');
            if ($stored !== '') {
                $info = @password_get_info($stored);
                $ok = (is_array($info) && !empty($info['algo']))
                    ? password_verify($password, $stored)
                    : hash_equals($stored, $password);
            }
        }

        if (!$user || !$ok) {
            $msg = 'Неверные имя пользователя или пароль';
            $s = $request->session();
            $s->put('error', $msg);        $s->flash('error', $msg);
            $s->put('error_list', [$msg]); $s->flash('error_list', [$msg]);
            return redirect('/login', 302);
        }

        // Canonical: log in by user ID; do not persist full user in session.
        Auth::login((string)$user['id']);

        $s = $request->session();
        $s->forget('_old_input');
        $to = $s->pull('redirect_to', '/');
        return redirect($to ?: '/', 302);
    }

    /**
     * Show register form.
     *
     * @param Request $request
     * @return Response
     */
    public function showRegisterForm(Request $request): Response
    {
        $s        = $request->session();
        $old      = $s->get('_old_input') ?: [];
        $username = is_array($old) ? (string)($old['username'] ?? '') : '';

        $pageVM = RegisterPageVM::fromArray([
            'title'            => 'Регистрация',
            'csrf'             => csrf_token(),
            'action'           => '/register',
            'prefill_username' => $username,
        ]);

        $layoutService = new LayoutService();
        $layoutVM = $layoutService->build($request, [
            'title'      => 'Регистрация',
            'nav_active' => 'login',
        ]);

        $flash = [
            'error'   => $s->get('error_list') ?: ($s->get('error') ? [(string)$s->get('error')] : []),
            'success' => [],
        ];

        return response()->view('auth.register', [
            'vm'     => $pageVM->toArray(),
            'layout' => $layoutVM->toArray(),
            'flash'  => $flash,
        ]);
    }

    /**
     * POST /register — validate/create/sign in.
     *
     * Controller remains thin: on success, service is called with user ID only.
     *
     * @param Request $request
     * @return Response
     */
    public function register(Request $request): Response
    {
        $rawUsername = (string) $request->input('username');
        $password    = (string) $request->input('password');
        $confirm     = (string) $request->input('password_confirmation');

        $request->session()->put('_old_input', ['username' => $rawUsername]);
        $request->session()->put('username', $rawUsername);

        $validator = Validator::make(
            ['username' => $rawUsername],
            ['username' => 'required|string|min:3|max:30']
        );

        $errors = [];
        if ($validator->fails()) {
            foreach ($validator->errors() as $fieldErrors) {
                foreach ($fieldErrors as $msg) { $errors[] = $msg; }
            }
        }

        $username = strtolower(trim($rawUsername));
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
            $errors[] = 'Имя пользователя: 3–30 символов, только латиница, цифры и _.';
        }

        $plen = strlen($password);
        if ($plen < 8)   { $errors[] = 'Пароль должен быть не короче 8 символов.'; }
        if ($plen > 100) { $errors[] = 'Пароль не должен превышать 100 символов.'; }
        if ($password !== $confirm) {
            $errors[] = 'Пароли не совпадают.';
        }
        if (DB::table('users')->where('username', '=', $username)->exists()) {
            $errors[] = 'Пользователь с таким именем уже существует.';
        }

        if (!empty($errors)) {
            $s = $request->session();
            $s->put('error', $errors[0]);     $s->flash('error', $errors[0]);
            $s->put('error_list', $errors);   $s->flash('error_list', $errors);
            return redirect('/register', 302);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now  = time();
        $uuid = self::uuidv4();

        try {
            DB::table('users')->insert([
                'id'           => $uuid,
                'username'     => $username,
                'password'     => $hash,
                'registered'   => $now,
                'reputation'   => 0,
                'group_id'     => 1,
                'last_visit'   => $now,
                'last_post'    => null,
                'role_id'      => 1,
                'language_id'  => 1,
                'title'        => null,
                'style'        => 0,
                'signature'    => null,
                'settings'     => json_encode(['avatar_shape' => 'square'], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (PDOException $e) {
            if (isset($e->errorInfo[0]) && $e->errorInfo[0] === '23000') {
                $msg = 'Пользователь с таким именем уже существует.';
                $s = $request->session();
                $s->put('error', $msg);        $s->flash('error', $msg);
                $s->put('error_list', [$msg]); $s->flash('error_list', [$msg]);
                return redirect('/register', 302);
            }
            throw $e;
        }

        // Read just created user and sign in by ID (no full user in session).
        $userRow = DB::table('users')->where('id', '=', $uuid)->first();
        /** @var array<string,mixed>|null $user */
        $user = $userRow ? (array)$userRow : null;

        if ($user) {
            Auth::login((string)$user['id']);
            $request->session()->forget('_old_input');
            return redirect('/', 302);
        }

        $msg = 'Не удалось создать пользователя.';
        $s = $request->session();
        $s->put('error', $msg);        $s->flash('error', $msg);
        $s->put('error_list', [$msg]); $s->flash('error_list', [$msg]);
        return redirect('/register', 302);
    }

    /**
     * Logout current user.
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        Auth::logout();
        return redirect('/', 302);
    }

    /**
     * Generate UUID v4.
     *
     * @return string
     */
    protected static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
