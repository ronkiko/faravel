<?php
// v0.3.117 — AdminCategoryController (tabs-ready)
// Рендерит единый Blade: 'admin.categories' + пробрасывает 'tab'.
// Логика CRUD не тронута.

namespace App\Http\Controllers;

use App\Services\Auth\AdminVisibilityPolicy;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;

class AdminCategoryController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        // Ленивый резолв — совместимо с текущим роутером без DI-контейнера.
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Авторизация на админку */
    protected function authorize(): ?Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/login');
        }
        $roleId = (int)($u['role_id'] ?? 0);

        if (method_exists($this->adminPolicy, 'canManageCategories')) {
            if (!$this->adminPolicy->canManageCategories($roleId)) {
                return response('Forbidden', 403);
            }
            return null;
        }

        if ($roleId < 3) {
            return response('Forbidden', 403);
        }
        return null;
    }

    public function index(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $cats = DB::table('categories')
            ->orderBy('order_id', 'asc')
            ->orderBy('title', 'asc')
            ->get();

        // список групп для селекта (на случай, если вкладка "Правка" будет открыта ссылкой)
        $groups = DB::select("SELECT id, name FROM groups ORDER BY id ASC");

        $tabParam = (string)$request->get('tab', '');
        $tab = ($tabParam === 'create') ? 'create' : 'index';

        return response()->view('admin.categories', [
            'categories' => $cats,
            'groups'     => $groups,
            'tab'        => $tab,
        ]);
    }

    /**
     * Создание категории.
     * - order_id = MAX(order_id) + 1
     * - slug: транслитерация + UNIQUE через индекс (ретраи без SELECT)
     */
    public function store(Request $request): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $data = $request->only(['name', 'description']);

        $validator = Validator::make($data, [
            'name'        => 'required|string|max:255',
            'description' => 'string|max:1000',
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
            return redirect('/admin/categories');
        }

        $title = (string)$data['name'];
        $desc  = trim((string)($data['description'] ?? ''));
        $uuid  = $this->uuidv4();

        $baseSlug = function_exists('slugify')
            ? slugify($title, '-', 100)
            : $this->fallbackSlugify($title, '-', 100);

        $pdo = DB::connection();
        $maxOrder  = (int)$pdo->query("SELECT COALESCE(MAX(order_id), 0) FROM categories")->fetchColumn();
        $nextOrder = $maxOrder + 1;

        $slug = $baseSlug !== '' ? $baseSlug : 'cat';
        for ($i = 0; $i < 25; $i++) {
            try {
                DB::table('categories')->insert([
                    'id'          => $uuid,
                    'slug'        => $slug,
                    'title'       => $title,
                    'description' => ($desc === '' ? null : $desc),
                    'order_id'    => $nextOrder,
                ]);

                $request->session()->flash('success', 'Категория создана.');
                return redirect('/admin/categories');
            } catch (\Throwable $e) {
                if (!$this->isDuplicateSlugError($e)) {
                    throw $e;
                }
                $slug = $this->withSuffix($baseSlug, $i + 2, 100);
            }
        }

        $request->session()->flash('error', 'Не удалось подобрать уникальный slug.');
        return redirect('/admin/categories');
    }

    public function edit(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $cat = DB::table('categories')->where('id', '=', $id)->first();
        if (!$cat) {
            return response('Not Found', 404);
        }

        $cats = DB::table('categories')
            ->orderBy('order_id', 'asc')
            ->orderBy('title', 'asc')
            ->get();

        $groups = DB::select("SELECT id, name FROM groups ORDER BY id ASC");

        return response()->view('admin.categories', [
            'categories' => $cats,
            'groups'     => $groups,
            'cat'        => $cat,
            'tab'        => 'edit',
        ]);
    }

    /**
     * Сохранение редактирования (ID неизменяем).
     * При конфликте уникальности slug — показываем ошибку (без автоподстановок).
     */
    public function update(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $cat = DB::table('categories')->where('id', '=', $id)->first();
        if (!$cat) {
            return response('Not Found', 404);
        }

        $data = $request->only(['id', 'title', 'slug', 'description', 'order_id', 'is_visible', 'min_group']);

        $postedId = (string)($data['id'] ?? '');
        if ($postedId !== $id) {
            $request->session()->flash('error', 'ID изменять запрещено.');
            return redirect('/admin/categories/' . $id . '/edit');
        }

        $validator = Validator::make($data, [
            'title'       => 'required|string|max:255',
            'slug'        => 'nullable|string|max:100',
            'description' => 'string|max:65535',
            'order_id'    => 'required|numeric|min:1|max:255',
            'is_visible'  => 'numeric|min:0|max:1',
            'min_group'   => 'required|numeric|min:0|max:255',
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
            return redirect('/admin/categories/' . $id . '/edit');
        }

        $newSlug = trim((string)($data['slug'] ?? ''));
        if ($newSlug === '') {
            $newSlug = function_exists('slugify')
                ? slugify((string)$data['title'], '-', 100)
                : $this->fallbackSlugify((string)$data['title'], '-', 100);
        } else {
            $newSlug = function_exists('slugify')
                ? slugify($newSlug, '-', 100)
                : $this->fallbackSlugify($newSlug, '-', 100);
        }

        $upd = [
            'title'       => (string)$data['title'],
            'slug'        => $newSlug,
            'description' => ($data['description'] === '' ? null : (string)$data['description']),
            'order_id'    => (int)$data['order_id'],
            'is_visible'  => isset($data['is_visible']) ? (int)$data['is_visible'] : 0,
            'min_group'   => (int)$data['min_group'],
        ];

        try {
            DB::table('categories')->where('id', '=', $id)->update($upd);
        } catch (\Throwable $e) {
            if ($this->isDuplicateSlugError($e)) {
                $request->session()->flash('error', 'Slug уже занят другой категорией.');
                return redirect('/admin/categories/' . $id . '/edit');
            }
            throw $e;
        }

        $request->session()->flash('success', 'Категория обновлена.');
        return redirect('/admin/categories');
    }

    /** Удаление категории */
    public function destroy(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) {
            return $resp;
        }

        $cat = DB::table('categories')->where('id', '=', $id)->first();
        if (!$cat) {
            return response('Not Found', 404);
        }

        $postedId = (string)$request->input('id', '');
        if ($postedId !== $id) {
            $request->session()->flash('error', 'Неверный ID для удаления.');
            return redirect('/admin/categories');
        }

        try {
            $deleted = DB::table('categories')->where('id', '=', $id)->delete();
            if ($deleted > 0) {
                $request->session()->flash('success', 'Категория удалена.');
            } else {
                $request->session()->flash('error', 'Не удалось удалить категорию.');
            }
        } catch (\Throwable $e) {
            $request->session()->flash('error', 'Нельзя удалить категорию: есть связанные записи.');
        }

        return redirect('/admin/categories');
    }

    // ====== helpers ======

    protected function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected function fallbackSlugify(string $value, string $sep = '-', int $maxLen = 100): string
    {
        $v = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        $map = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'ї' => 'yi',
            'і' => 'i',
            'є' => 'ye',
            'ґ' => 'g',
        ];
        $v = strtr($v, $map);
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
            if (is_string($tmp) && $tmp !== '') {
                $v = $tmp;
            }
        }
        $v = preg_replace('/[^a-z0-9]+/i', $sep, (string)$v);
        $quoted = preg_quote($sep, '/');
        $v = preg_replace('/' . $quoted . '+/', $sep, (string)$v);
        $v = trim((string)$v, $sep . ' _.');
        if ($maxLen > 0 && strlen($v) > $maxLen) {
            $v = substr($v, 0, $maxLen);
            $v = rtrim($v, $sep);
        }
        return $v === '' ? 'n-a' : $v;
    }

    protected function isDuplicateSlugError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        if (strpos($msg, '1062') !== false && strpos($msg, 'slug') !== false) {
            return true;
        }
        if (strpos($msg, 'duplicate entry') !== false && strpos($msg, 'slug') !== false) {
            return true;
        }
        return false;
    }

    protected function withSuffix(string $base, int $n, int $maxLen = 100): string
    {
        $suffix = '-' . $n;
        $trimTo = max(1, $maxLen - strlen($suffix));
        $cut    = substr($base, 0, $trimTo);
        $cut    = rtrim($cut, '-');
        return ($cut !== '' ? $cut : 'n-a') . $suffix;
    }
}
