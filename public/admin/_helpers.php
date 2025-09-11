<?php // v0.4.2
/* public/admin/_helpers.php
Назначение: вспомогательные функции SafeMode-админки (сессия, рендер, утилиты).
FIX: добавлена фабрика admin_make_database_admin() с авто-конфигурацией DatabaseAdmin
     без зависимости от наличия конструктора у класса; минорные правки PHPDoc.
*/

declare(strict_types=1);

/**
 * Start session safely for admin area.
 *
 * Preconditions:
 * - Headers must not be sent before the call.
 *
 * Side effects:
 * - Starts PHP session if not active.
 *
 * @return void
 */
function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Basic, cookie-only sessions; can be tuned later in php.ini / ini_set.
        session_start();
    }
}

/**
 * Resolve admin key from environment with priority:
 * SERVICEMODE_KEY → ADMIN_KEY → '' (not set).
 *
 * Why: Admin area must be locked down by a shared secret set via ENV.
 *
 * @return string Non-empty if a key is present, empty string otherwise.
 */
function admin_resolve_key(): string
{
    // Try framework env() helper if present; fallback to getenv().
    $svc = function (string $k): ?string {
        if (function_exists('env')) {
            /** @var string|null $v */
            $v = env($k);
            return $v !== null ? trim((string)$v) : null;
        }
        $v = getenv($k);
        return $v !== false ? trim((string)$v) : null;
    };

    $key = $svc('SERVICEMODE_KEY') ?? '';
    if ($key === '') {
        $key = $svc('ADMIN_KEY') ?? '';
    }
    return $key;
}

/**
 * Check if current session is authorized for admin area.
 *
 * @return bool True when authorized in this session.
 */
function admin_is_authorized(): bool
{
    return isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'] === true;
}

/**
 * Mark current session as authorized.
 *
 * Side effects:
 * - Writes flag into $_SESSION.
 *
 * @return void
 */
function admin_mark_authorized(): void
{
    $_SESSION['admin_auth'] = true;
}

/**
 * Remove authorization flag and destroy session.
 *
 * Side effects:
 * - Unsets session flag and regenerates session id.
 *
 * @return void
 */
function admin_logout(): void
{
    unset($_SESSION['admin_auth']);
    // Regenerate id to avoid fixation. Keep other session data if any.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Issue a local redirect and stop execution.
 *
 * @param string $path Relative path like 'index.php?page=home'.
 *
 * Preconditions:
 * - No output must be sent prior to calling this function.
 *
 * @return void
 */
function admin_redirect(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

/**
 * Render page layout start (header + sidebar + content wrapper).
 *
 * View-only: contains only HTML/CSS, no business logic.
 *
 * @param string               $title  Page title.
 * @param array<string,string> $menu   Map of label => href.
 * @param string               $active Active page key used for highlighting.
 *
 * @return void
 */
function admin_layout_start(string $title, array $menu, string $active): void
{
    // Basic, unobtrusive style without JS. Keep CSS minimal and readable.
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<style>
    *{box-sizing:border-box}body{margin:0;font:14px/1.4 system-ui,Segoe UI,Arial}
    header{background:#222;color:#fff;padding:10px 16px;display:flex;gap:12px;align-items:center}
    header .grow{flex:1}
    .wrap{display:flex;min-height:calc(100vh - 52px)}
    nav{width:220px;background:#f5f5f7;border-right:1px solid #ddd;padding:12px}
    nav a{display:block;padding:8px 10px;border-radius:6px;text-decoration:none;color:#222}
    nav a.active{background:#dfe8ff;border:1px solid #b9ccff}
    main{flex:1;padding:16px}
    .badge{font-size:12px;padding:2px 6px;border-radius:4px;background:#444;color:#fff}
    .alert{padding:10px 12px;border-radius:6px;border:1px solid}
    .alert.info{background:#eef5ff;border-color:#b9ccff}
    .alert.error{background:#ffecec;border-color:#ffc2c2}
    .panel{border:1px solid #ddd;border-radius:8px;overflow:hidden;margin-bottom:16px}
    .panel .hd{background:#fafafa;border-bottom:1px solid #eee;padding:10px 12px}
    .panel .bd{padding:12px}
    form.inline > *{margin-right:8px}
    button{padding:8px 12px;border-radius:6px;border:1px solid #888;background:#fff;cursor:pointer}
    input[type=password]{padding:8px 10px;border-radius:6px;border:1px solid #bbb;width:240px}
    .muted{color:#666}
    </style></head><body>';
    echo '<header><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>';
    echo '<span class="badge">SafeMode</span><div class="grow"></div>';
    echo '<a class="badge" href="index.php?logout=1" ';
    echo 'style="text-decoration:none;background:#900">Выйти</a></header>';
    echo '<div class="wrap"><nav>';
    foreach ($menu as $label => $href) {
        $isActive = (strpos($href, 'page=' . $active) !== false);
        echo '<a class="' . ($isActive ? 'active' : '') . '" href="' . htmlspecialchars($href) . '">'
           . htmlspecialchars($label) . '</a>';
    }
    echo '</nav><main>';
}

/**
 * Render layout end (closing wrappers).
 *
 * @return void
 */
function admin_layout_end(): void
{
    echo '</main></div></body></html>';
}

/**
 * Render an alert box.
 *
 * @param 'info'|'error' $type    Alert type for styling.
 * @param string         $message Human-friendly message to display.
 *
 * @return void
 *
 * @example admin_alert('info', 'Everything is OK');
 */
function admin_alert(string $type, string $message): void
{
    $type = $type === 'error' ? 'error' : 'info';
    echo '<div class="alert ' . $type . '">'
       . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
       . '</div>';
}

/**
 * Render simple login screen.
 *
 * @param bool        $hasKey True when a key is configured in ENV.
 * @param string|null $error  Optional error message to show above the form.
 *
 * @return void
 */
function admin_render_login(bool $hasKey, ?string $error = null): void
{
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Faravel Admin: вход</title>';
    echo '<style>
    body{margin:0;font:14px/1.4 system-ui,Segoe UI,Arial;background:#f7f7fa}
    .box{max-width:560px;margin:12vh auto;background:#fff;border:1px solid #ddd;border-radius:10px}
    .hd{padding:14px 16px;border-bottom:1px solid #eee;background:#fafafa}
    .bd{padding:16px}
    .alert{padding:10px 12px;border-radius:6px;border:1px solid}
    .alert.info{background:#eef5ff;border-color:#b9ccff}
    .alert.error{background:#ffecec;border-color:#ffc2c2}
    form.inline > *{margin-right:8px}
    input[type=password]{padding:8px 10px;border-radius:6px;border:1px solid #bbb;width:260px}
    button{padding:8px 12px;border-radius:6px;border:1px solid #888;background:#fff;cursor:pointer}
    .muted{color:#666}
    </style></head><body>';
    echo '<div class="box"><div class="hd"><strong>Faravel SafeMode Admin</strong></div><div class="bd">';
    if ($error !== null) {
        admin_alert('error', $error);
    }
    if (!$hasKey) {
        admin_alert(
            'error',
            'Ключ админки не задан. Установите SERVICEMODE_KEY или ADMIN_KEY в .env/ENV.'
        );
        echo '<p class="muted">Доступ запрещён до задания ключа.</p>';
    } else {
        echo '<form method="post" class="inline" autocomplete="off">';
        echo '<label>Ключ доступа: <input type="password" name="admin_key" required></label>';
        echo '<button type="submit">Войти</button></form>';
        echo '<p class="muted">Ключ проверяется один раз за сессию.</p>';
    }
    echo '</div></div></body></html>';
}

/**
 * Build DatabaseAdmin instance resiliently to constructor signature.
 *
 * Why: In this project DatabaseAdmin has no __construct(). We instantiate it with no args,
 * then pass config via one of known mutators if present.
 *
 * @param array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * } $cfg
 *
 * @return object Instance of \Faravel\Database\DatabaseAdmin (typed as object for resilience).
 *
 * @throws RuntimeException If class does not exist.
 */
function admin_make_database_admin(array $cfg): object
{
    $class = '\\Faravel\\Database\\DatabaseAdmin';
    if (!class_exists($class)) {
        throw new RuntimeException('DatabaseAdmin class not found: ' . $class);
    }

    $ref  = new ReflectionClass($class);
    $ctor = $ref->getConstructor();

    // 1) Instantiate safely (prefer no-arg).
    try {
        $obj = ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0)
            ? $ref->newInstance()
            : $ref->newInstance(
                $cfg['driver'],
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['username'],
                $cfg['password']
            );
    } catch (ArgumentCountError $e) {
        // Fall back to no-arg if signature mismatched.
        $obj = $ref->newInstance();
    }

    // 2) Try to propagate config via known mutators.
    foreach (['configure', 'setConfig', 'withConfig'] as $m) {
        if ($ref->hasMethod($m)) {
            $ref->getMethod($m)->invoke($obj, $cfg);
            break;
        }
    }

    return $obj;
}
