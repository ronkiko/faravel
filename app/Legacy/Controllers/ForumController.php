<?php // v0.3.119
/*
Форум на «taggable hubs». UUID-маршруты, флеши через session()->flash().
FIX: методы возвращают Faravel\Http\Response (response()->view()).
FIX: генерация id через глобальный хелпер uuid() вместо DB::uuid().
FIX: корректная работа с Auth::user() (array|object) через userId().
*/

namespace App\Http\Controllers;

use Faravel\Support\Facades\DB;
use Faravel\Support\Facades\Auth;
use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Tag\TagParser;
use App\Services\Tag\TagWriteService;
use App\Services\Tag\TagReadService;
use App\Services\Support\Slugger;

class ForumController
{
    public function __construct(
        private TagParser $parser = new TagParser(),
        private TagWriteService $tagWrite = new TagWriteService(),
        private TagReadService $tagRead = new TagReadService(),
    ) {}

    /* ===================== Служебное ===================== */

    private function userId($user): ?string
    {
        if (is_array($user)) {
            return $user['id'] ?? null;
        }
        if (is_object($user)) {
            return $user->id   ?? null;
        }
        return null;
    }

    private function slugUniqueForTopic(string $title): string
    {
        $base = Slugger::make($title, '-', 160);
        if ($base === '' || $base === 'n-a') $base = 'topic';
        $slug = $base;
        $i = 2;
        while (DB::table('topics')->where('slug', '=', $slug)->first()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /* ===================== Паблик: страницы ===================== */

    /** Главная форума — список видимых категорий. */
    public function index(Request $r): Response
    {
        $cats = DB::select("
            SELECT id, slug, title, description, is_visible, order_id
            FROM categories
            WHERE is_visible = 1
            ORDER BY COALESCE(order_id, 999), title ASC
        ");
        return response()->view('forum.index', [
            'categories' => $cats,
            'user'       => Auth::user(),
        ]);
    }

    /** Категория: витрина 10 топ-тегов (хабов) по категории. */
    public function category(Request $r, string $categoryId): Response
    {
        $cat = DB::table('categories')->where('id', '=', $categoryId)->first();
        if (!$cat) {
            session()->flash('error', 'Категория не найдена.');
            return redirect('/forum');
        }

        $tags = $this->tagRead->topTagsByCategory($categoryId, 10);

        return response()->view('forum.category', [
            'category' => $cat,
            'topTags'  => $tags,
            'user'     => Auth::user(),
        ]);
    }

    /** Хаб: список тем по UUID тега. /forum/h/{tagId}/ */
    public function hubList(Request $r, string $tagId): Response
    {
        $get = static function ($row, string $k) {
            if (is_array($row))  return $row[$k] ?? null;
            if (is_object($row)) return $row->$k ?? null;
            return null;
        };

        $tag = $this->tagRead->getTagById($tagId);
        if (!$tag || (int)($get($tag, 'is_active') ?? 0) !== 1) {
            session()->flash('error', 'Тег не найден или неактивен.');
            return redirect('/forum');
        }

        $page = max(1, (int)$r->input('page', 1));
        $per  = min(50, max(5, (int)$r->input('per', 20)));

        $data = $this->tagRead->listTopicsByTagId($tagId, $page, $per);

        return response()->view('forum.hub', [
            'tag'   => $tag,
            'items' => $data['items'],
            'page'  => $page,
            'pages' => $data['pages'],
            'total' => $data['total'],
        ]);
    }

    /** Просмотр темы вне контекста хаба. /forum/t/{topicId} */
    public function topic(Request $r, string $topicId): Response
    {
        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) {
            session()->flash('error', 'Тема не найдена.');
            return redirect('/forum');
        }

        $posts = DB::select("
            SELECT p.id, p.user_id, p.content, p.created_at, p.updated_at, p.is_deleted
            FROM posts p
            WHERE p.topic_id = ?
            ORDER BY p.created_at ASC
        ", [$topicId]);

        $pills = $this->tagRead->topicTagPills($topicId);
        $category = !empty($topic->category_id)
            ? DB::table('categories')->where('id', '=', $topic->category_id)->first()
            : null;

        $warning = session()->pull('error') ?: null;

        return response()->view('forum.topic', [
            'topic'     => $topic,
            'category'  => $category,
            'posts'     => $posts,
            'tagPills'  => $pills,
            'user'      => Auth::user(),
            'warning'   => $warning,
        ]);
    }

    /** Просмотр темы в контексте хаба. /forum/h/{tagId}/t/{topicId} */
    public function hubTopic(Request $r, string $tagId, string $topicId): Response
    {
        $tag = $this->tagRead->getTagById($tagId);
        if (!$tag) {
            session()->flash('error', 'Тег не найден.');
            return redirect('/forum');
        }

        if (!$this->tagRead->ensureTopicHasTag($topicId, $tagId)) {
            session()->flash('error', 'Тема не относится к выбранному хабу.');
            return redirect('/forum/h/' . $tagId . '/');
        }

        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) {
            session()->flash('error', 'Тема не найдена.');
            return redirect('/forum/h/' . $tagId . '/');
        }

        $posts = DB::select("
            SELECT p.id, p.user_id, p.content, p.created_at, p.updated_at, p.is_deleted
            FROM posts p
            WHERE p.topic_id = ?
            ORDER BY p.created_at ASC
        ", [$topicId]);

        $pills = $this->tagRead->topicTagPills($topicId);
        $category = !empty($topic->category_id)
            ? DB::table('categories')->where('id', '=', $topic->category_id)->first()
            : null;

        $warning = session()->pull('error') ?: null;

        return response()->view('forum.topic', [
            'topic'     => $topic,
            'category'  => $category,
            'posts'     => $posts,
            'tagPills'  => $pills,
            'user'      => Auth::user(),
            'warning'   => $warning,
        ]);
    }

    /* ===================== Создание/ответ ===================== */

    public function create(Request $r): Response
    {
        $tags = DB::select("SELECT id, slug, title, color FROM tags WHERE is_active=1 ORDER BY title ASC");
        $cats = DB::select("SELECT id, title FROM categories ORDER BY title ASC");
        return response()->view('forum.create_topic', [
            'categories' => $cats,
            'tags'       => $tags,
            'user'       => Auth::user(),
        ]);
    }

    public function store(Request $r): Response
    {
        $user = Auth::user();
        $uid  = $this->userId($user);
        if (!$uid) {
            session()->flash('error', 'Войдите, чтобы создать тему.');
            return redirect('/login');
        }

        $title      = trim((string)$r->input('title'));
        $content    = trim((string)$r->input('content'));
        $categoryId = (string)$r->input('category_id');

        if ($title === '' || $content === '' || $categoryId === '') {
            session()->flash('error', 'Заполните все поля.');
            return redirect('/forum/create');
        }

        $now       = time();
        $topicId   = uuid();
        $topicSlug = $this->slugUniqueForTopic($title);

        DB::insert(
            "INSERT INTO topics(id, category_id, title, slug, posts_count, last_post_id, last_post_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NULL, ?, ?, ?)",
            [$topicId, $categoryId, $title, $topicSlug, $now, $now, $now]
        );

        $postId = uuid();
        DB::insert(
            "INSERT INTO posts(id, topic_id, user_id, content, created_at, updated_at, is_deleted)
             VALUES (?, ?, ?, ?, ?, ?, 0)",
            [$postId, $topicId, $uid, $content, $now, $now]
        );

        DB::update(
            "UPDATE topics SET last_post_id=?, last_post_at=?, updated_at=? WHERE id=?",
            [$postId, $now, $now, $topicId]
        );

        // Теги
        $selectedSlugs = array_map('strval', (array)$r->input('tags', []));
        $autoSlugs     = array_unique(array_merge(
            $this->parser->extractSlugs($title),
            $this->parser->extractSlugs($content)
        ));
        $allSlugs = array_values(array_unique(array_merge($selectedSlugs, $autoSlugs)));

        $slugToId = $this->tagWrite->upsertPending($allSlugs, $uid);
        $tagIds   = array_values($slugToId);

        $this->tagWrite->attachToTopic($topicId, $tagIds);
        $this->tagWrite->attachToPost($postId, $topicId, $tagIds);

        // Универсальная страница темы (вне контекста хаба)
        return redirect('/forum/t/' . $topicId);
    }

    public function reply(Request $r, string $topicId): Response
    {
        $user = Auth::user();
        $uid = is_array($user) ? (string)($user['id'] ?? '') : (string)($user->id ?? '');
        if (!$uid) {
            session()->flash('error', 'Войдите, чтобы ответить.');
            return redirect('/login');
        }

        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) {
            session()->flash('error', 'Тема не найдена.');
            return redirect('/forum');
        }

        $content = trim((string)$r->input('content'));
        if ($content === '') {
            session()->flash('error', 'Сообщение пустое.');
            return redirect('/forum');
        }

        $now    = time();
        $postId = uuid();

        DB::insert(
            "INSERT INTO posts(id, topic_id, user_id, content, created_at, updated_at, is_deleted)
             VALUES (?, ?, ?, ?, ?, ?, 0)",
            [$postId, $topicId, $uid, $content, $now, $now]
        );

        DB::update(
            "UPDATE topics SET posts_count = posts_count + 1, last_post_id=?, last_post_at=?, updated_at=? WHERE id=?",
            [$postId, $now, $now, $topicId]
        );

        $autoSlugs = $this->parser->extractSlugs($content);
        if ($autoSlugs) {
            $slugToId = $this->tagWrite->findExistingBySlugs($autoSlugs);
            $this->tagWrite->attachToPost($postId, $topicId, array_values($slugToId));
        }

        $returnTo = trim((string)$r->input('return_to'));
        if ($returnTo !== '') {
            return redirect($returnTo);
        }

        $hubTagId = (string)(DB::scalar(
            "SELECT tag_id FROM taggables WHERE entity='topic' AND topic_id=? ORDER BY created_at ASC LIMIT 1",
            [$topicId]
        ) ?? '');
        if ($hubTagId !== '') {
            return redirect('/forum/h/' . $hubTagId . '/t/' . $topicId);
        }
        return redirect('/forum');
    }
}
