<?php
// v0.3.114 — FIX: categories[] читаем через input(), а не only(); связи category_forum сохраняются.
// Никакой бизнес-логики/маршрутов не меняли, валидацию оставили прежней.

namespace App\Http\Controllers;

use App\Services\Auth\AdminVisibilityPolicy;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Validator;

class AdminForumController
{
    /** @var AdminVisibilityPolicy */
    private AdminVisibilityPolicy $adminPolicy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        // Ленивый резолв: не требуем DI, совместимо с текущим роутером.
        $this->adminPolicy = $policy ?? new AdminVisibilityPolicy();
    }

    /** Авторизация на админку (defense-in-depth, помимо middleware AdminOnly) */
    protected function authorize(): ?Response
    {
        $u = Auth::user();
        if (!$u) {
            return redirect('/login');
        }
        $roleId = (int)($u['role_id'] ?? 0);
        if (!$this->adminPolicy->canManageForums($roleId)) {
            return response('Forbidden', 403);
        }
        return null;
    }

    /** Список форумов */
    public function index(Request $request): Response
    {
        if ($resp = $this->authorize()) { return $resp; }

        $forums = DB::select("
            SELECT f.*, p.title AS parent_title
            FROM forums f
            LEFT JOIN forums p ON p.id = f.parent_forum_id
            ORDER BY (f.parent_forum_id IS NULL), f.parent_forum_id,
                     f.order_id IS NULL, f.order_id, f.title
        ");

        $pivot = DB::select("
            SELECT cf.forum_id, c.title
            FROM category_forum cf
            JOIN categories c ON c.id = cf.category_id
            ORDER BY cf.position IS NULL, cf.position, c.title
        ");
        $catsByForum = [];
        foreach ($pivot as $row) {
            $fid = (string)$row['forum_id'];
            $catsByForum[$fid][] = (string)$row['title'];
        }

        $groups = DB::select("SELECT id, name FROM groups ORDER BY id ASC");

        return response()->view('admin.forums.index', [
            'forums'       => $forums,
            'catsByForum'  => $catsByForum,
            'groups'       => $groups,
            'flash'        => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    /** Форма создания */
    public function create(Request $request): Response
    {
        if ($resp = $this->authorize()) { return $resp; }
        return $this->renderForm($request, null);
    }

    /** Форма редактирования */
    public function edit(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) { return $resp; }

        $forum = DB::table('forums')->where('id', '=', $id)->first();
        if (!$forum) {
            $request->session()->flash('error', 'Форум не найден.');
            return redirect('/admin/forums');
        }
        return $this->renderForm($request, $forum);
    }

    /** Создание */
    public function store(Request $request): Response
    {
        if ($resp = $this->authorize()) { return $resp; }

        $data = $request->only([
            'title','slug','description','parent_forum_id','order_id',
            'is_visible','is_locked','min_group','categories' // оставляем для совместимости с валидацией
        ]);
        // ВАЖНО: массив категорий читаем напрямую (only() часто режет массивы):
        $categories = $request->input('categories', []);
        if (!is_array($categories)) { $categories = $categories !== null ? [(string)$categories] : []; }

        $v = $this->validator($data);
        if ($v->fails()) {
            $msg = $this->firstError($v->errors());
            $request->session()->flash('error', $msg);
            return redirect('/admin/forums/new');
        }

        $id    = $this->uuidv4();
        $slug  = $this->normalizeSlug($data['slug'] ?: $data['title']);
        if (!$this->slugIsUnique($slug)) {
            $request->session()->flash('error', 'Слаг уже занят.');
            return redirect('/admin/forums/new');
        }

        $parentId = $this->nullIfEmpty($data['parent_forum_id'] ?? null);
        if ($parentId === $id) { $parentId = null; }

        [$path, $depth] = $this->computePathDepth($parentId);

        $now = time();
        DB::beginTransaction();
        try {
            DB::insert("
                INSERT INTO forums (
                    id, slug, title, description, parent_forum_id, path, depth, order_id,
                    is_visible, is_locked, min_group, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $id, $slug, $data['title'], $data['description'] ?? null, $parentId,
                $path, $depth,
                $this->nullIfEmpty($data['order_id'] ?? null),
                (int)!empty($data['is_visible']), (int)!empty($data['is_locked']),
                (int)($data['min_group'] ?? 0),
                $now, $now
            ]);

            // Сохраняем привязки к категориям
            $this->syncCategories($id, $categories);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $request->session()->flash('error', 'Ошибка при создании форума.');
            return redirect('/admin/forums/new');
        }

        $request->session()->flash('success', 'Форум создан.');
        return redirect('/admin/forums');
    }

    /** Обновление */
    public function update(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) { return $resp; }

        $data = $request->only([
            'title','slug','description','parent_forum_id','order_id',
            'is_visible','is_locked','min_group','categories' // оставляем для совместимости с валидацией
        ]);
        // ВАЖНО: массив категорий читаем напрямую
        $categories = $request->input('categories', []);
        if (!is_array($categories)) { $categories = $categories !== null ? [(string)$categories] : []; }

        $v = $this->validator($data, $id);
        if ($v->fails()) {
            $msg = $this->firstError($v->errors());
            $request->session()->flash('error', $msg);
            return redirect('/admin/forums/'.$id.'/edit');
        }

        $forum = DB::table('forums')->where('id', '=', $id)->first();
        if (!$forum) {
            $request->session()->flash('error', 'Форум не найден.');
            return redirect('/admin/forums');
        }

        $slug = $this->normalizeSlug($data['slug'] ?: $data['title']);
        if (!$this->slugIsUnique($slug, $id)) {
            $request->session()->flash('error', 'Слаг уже занят.');
            return redirect('/admin/forums/'.$id.'/edit');
        }

        $parentId = $this->nullIfEmpty($data['parent_forum_id'] ?? null);
        if ($parentId === $id) {
            $request->session()->flash('error', 'Родительский форум не может совпадать с текущим.');
            return redirect('/admin/forums/'.$id.'/edit');
        }

        $now = time();

        DB::beginTransaction();
        try {
            $parentChanged = ((string)($forum['parent_forum_id'] ?? '')) !== (string)($parentId ?? '');
            if ($parentChanged) {
                [$path, $depth] = $this->computePathDepth($parentId);
            } else {
                $path  = (string)$forum['path'];
                $depth = (int)$forum['depth'];
            }

            DB::statement("
                UPDATE forums SET
                    slug=?, title=?, description=?, parent_forum_id=?, path=?, depth=?,
                    order_id=?, is_visible=?, is_locked=?, min_group=?, updated_at=?
                WHERE id=?
            ", [
                $slug, $data['title'], $data['description'] ?? null, $parentId,
                $path, $depth,
                $this->nullIfEmpty($data['order_id'] ?? null),
                (int)!empty($data['is_visible']), (int)!empty($data['is_locked']),
                (int)($data['min_group'] ?? 0),
                $now, $id
            ]);

            // Сохраняем привязки к категориям
            $this->syncCategories($id, $categories);

            if ($parentChanged) {
                $this->rebuildSubtreePaths($id);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $request->session()->flash('error', 'Ошибка при сохранении.');
            return redirect('/admin/forums/'.$id.'/edit');
        }

        $request->session()->flash('success', 'Изменения сохранены.');
        return redirect('/admin/forums');
    }

    /** Удаление */
    public function delete(Request $request, string $id): Response
    {
        if ($resp = $this->authorize()) { return $resp; }

        DB::beginTransaction();
        try {
            $childCount = (int)DB::scalar(
                "SELECT COUNT(*) FROM forums WHERE parent_forum_id = ?",
                [$id]
            );
            if ($childCount > 0) {
                DB::rollBack();
                $request->session()->flash(
                    'error',
                    'Сначала перенесите или удалите дочерние форумы.'
                );
                return redirect('/admin/forums');
            }

            try {
                DB::statement(
                    "UPDATE topics SET forum_id = NULL WHERE forum_id = ?",
                    [$id]
                );
            } catch (\Throwable $e) {
                // игнор: таблицы/полей может не быть — зависит от миграций
            }

            DB::statement("DELETE FROM category_forum WHERE forum_id = ?", [$id]);
            DB::statement("DELETE FROM forums WHERE id = ?", [$id]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $request->session()->flash('error', 'Не удалось удалить форум.');
            return redirect('/admin/forums');
        }

        $request->session()->flash('success', 'Форум удалён.');
        return redirect('/admin/forums');
    }

    // ---------- helpers ----------

    private function renderForm(Request $request, $forum): Response
    {
        $categories = DB::select("SELECT id, title FROM categories ORDER BY title ASC");
        $groups     = DB::select("SELECT id, name FROM groups ORDER BY id ASC");
        $forumsList = DB::select("SELECT id, title FROM forums ORDER BY title ASC");

        // Контекстная категория (если открывали через ?category_id=...)
        $ctxCategory = null;
        if (!$forum) {
            $ctxId = (string)($request->get('category_id') ?? '');
            if ($ctxId !== '') {
                foreach ($categories as $c) {
                    if ((string)$c['id'] === $ctxId) {
                        $ctxCategory = ['id'=>$c['id'], 'title'=>$c['title']];
                        break;
                    }
                }
            }
        }

        // выбранные категории
        $selectedCategories = [];
        if ($forum) {
            $selected = DB::select(
                "SELECT category_id FROM category_forum
                 WHERE forum_id = ? ORDER BY position",
                [$forum['id']]
            );
            foreach ($selected as $row) {
                $selectedCategories[] = (string)$row['category_id'];
            }
        } elseif ($ctxCategory) {
            $selectedCategories[] = (string)$ctxCategory['id'];
        }

        // удобочитаемое имя родителя
        $parentTitle = '—';
        if ($forum && !empty($forum['parent_forum_id'])) {
            foreach ($forumsList as $opt) {
                if ((string)$opt['id'] === (string)$forum['parent_forum_id']) {
                    $parentTitle = (string)$opt['title'];
                    break;
                }
            }
        }

        return response()->view('admin.forums.form', [
            'mode'       => $forum ? 'edit' : 'create',
            'forum'      => $forum,
            'categories' => $categories,
            'groups'     => $groups,
            'forumsList' => $forumsList,
            'selectedCategories' => $selectedCategories,
            'ctxCategory'=> $ctxCategory,
            'parentTitle'=> $parentTitle,
            'flash'      => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    private function validator(array $data, ?string $id = null)
    {
        $rules = [
            'title'           => 'required|string|max:200',
            'slug'            => 'nullable|string|max:200',
            'description'     => 'nullable|string',
            'parent_forum_id' => 'nullable|string|max:36',
            'order_id'        => 'nullable|integer',
            'is_visible'      => 'nullable',
            'is_locked'       => 'nullable',
            'min_group'       => 'nullable|integer',
            'categories'      => 'nullable|array',
        ];
        return Validator::make($data, $rules);
    }

    private function firstError(array $errors): string
    {
        foreach ($errors as $field => $list) {
            if (!empty($list[0])) return (string)$list[0];
        }
        return 'Validation error';
    }

    private function normalizeSlug(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('~[^a-z0-9\p{L}\s\-_\/]+~u', '', $s);
        $s = preg_replace('~[\/\s\-_]+~u', '-', $s);
        $s = trim($s, '-');
        if ($s === '') $s = 'forum';
        if (mb_strlen($s, 'UTF-8') > 200) {
            $s = mb_substr($s, 0, 200, 'UTF-8');
        }
        return $s;
    }

    private function slugIsUnique(string $slug, ?string $ignoreId = null): bool
    {
        if ($ignoreId) {
            $cnt = (int)DB::scalar(
                "SELECT COUNT(*) FROM forums WHERE slug = ? AND id <> ?",
                [$slug, $ignoreId]
            );
        } else {
            $cnt = (int)DB::scalar(
                "SELECT COUNT(*) FROM forums WHERE slug = ?",
                [$slug]
            );
        }
        return $cnt === 0;
    }

    private function nullIfEmpty($v) {
        if ($v === '' || $v === null) return null;
        return $v;
    }

    /** Возвращает [path, depth] от родителя */
    private function computePathDepth(?string $parentId): array
    {
        if (!$parentId) return ['', 0];
        $p = DB::table('forums')->where('id', '=', $parentId)->first();
        if (!$p) return ['', 0];
        $path  = rtrim((string)$p['path'], '/').'/'.$parentId.'/';
        $depth = (int)$p['depth'] + 1;
        return [$path, $depth];
    }

    /** Пересобирает path/depth у поддерева (после смены родителя) */
    private function rebuildSubtreePaths(string $rootId): void
    {
        $all = DB::select("SELECT id, parent_forum_id, path, depth FROM forums");
        $byParent = [];
        foreach ($all as $row) {
            $pid = (string)($row['parent_forum_id'] ?? '');
            $byParent[$pid][] = $row;
        }

        $set = function ($id, $parentId, $path, $depth) use (&$set, &$byParent) {
            DB::statement("UPDATE forums SET path=?, depth=? WHERE id=?", [$path, $depth, $id]);
            $children = $byParent[$id] ?? [];
            foreach ($children as $ch) {
                $cid = (string)$ch['id'];
                $cpath = rtrim($path, '/').'/'.$id.'/';
                $cdepth = $depth + 1;
                $set($cid, $id, $cpath, $cdepth);
            }
        };

        [$path, $depth] = $this->computePathDepth($this->getParentId($rootId));
        $set($rootId, $this->getParentId($rootId), $path, $depth);
    }

    private function getParentId(string $id): ?string
    {
        $row = DB::table('forums')->where('id', '=', $id)->first();
        if (!$row) return null;
        $pid = $row['parent_forum_id'] ?? null;
        return $pid ? (string)$pid : null;
    }

    private function syncCategories(string $forumId, $categories): void
    {
        if (!is_array($categories)) { $categories = $categories !== null ? [(string)$categories] : []; }
        DB::statement("DELETE FROM category_forum WHERE forum_id = ?", [$forumId]);
        $pos = 1;
        foreach ($categories as $cid) {
            if (!$cid) continue;
            DB::insert(
                "INSERT INTO category_forum (category_id, forum_id, position)
                 VALUES (?, ?, ?)",
                [(string)$cid, $forumId, $pos++]
            );
        }
    }

    private function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
