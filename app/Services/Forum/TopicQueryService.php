<?php // v0.4.3
/* app/Services/Forum/TopicQueryService.php
Purpose: Сервис выборок для страницы темы. Инкапсулирует SQL, используя только методы,
которые поддерживает Faravel QueryBuilder. Возвращает «сырьё» для ViewModels.
FIX: Добавлен универсальный fetchByIds(); потребители переведены на него; PHPDoc и
     мелкие правки. Без whereIn/selectRaw.
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class TopicQueryService
{
    /**
     * Найти тему по slug, если нет — по id.
     *
     * @param string $topicIdOrSlug Непустой идентификатор.
     * @pre $topicIdOrSlug !== ''.
     * @side-effects Чтение БД.
     * @return array<string,mixed>|null
     */
    public function findTopicBySlugOrId(string $topicIdOrSlug): ?array
    {
        $row = DB::table('topics')->where('slug', '=', $topicIdOrSlug)->first();
        if (!$row) {
            $row = DB::table('topics')->where('id', '=', $topicIdOrSlug)->first();
        }
        return $row ? (array)$row : null;
    }

    /**
     * Лёгкая карточка категории (id, slug, title).
     *
     * @param string $categoryId UUID категории.
     * @pre $categoryId !== ''.
     * @side-effects Чтение БД.
     * @return array<string,mixed>|null
     */
    public function findCategoryLight(string $categoryId): ?array
    {
        if ($categoryId === '') {
            return null;
        }
        $row = DB::table('categories')
            ->select(['id', 'slug', 'title'])
            ->where('id', '=', $categoryId)
            ->first();
        return $row ? (array)$row : null;
    }

    /**
     * Список постов темы (без удалённых).
     *
     * @param string $topicId UUID темы.
     * @param int    $limit   1..500.
     * @pre $topicId !== ''.
     * @side-effects Чтение БД.
     * @return array<int,array<string,mixed>>
     */
    public function listPosts(string $topicId, int $limit = 100): array
    {
        $limit = \max(1, \min(500, $limit));
        $rows = DB::table('posts')
            ->where('topic_id', '=', $topicId)
            ->where('is_deleted', '=', 0)
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $out[] = (array)$r;
        }
        return $out;
    }

    /**
     * Универсальная выборка по массиву ID без whereIn().
     *
     * Делает N простых запросов `SELECT {columns} FROM $table WHERE $pk = ?`
     * и собирает ассоц.массив `id => row`. Работает для любых таблиц и ключей,
     * поддерживает выбор конкретных колонок. Централизует паттерн, когда в
     * Faravel QueryBuilder нет whereIn().
     *
     * @param string             $table    Имя таблицы (`users`, `groups` и т.п.).
     * @param string             $pk       Имя ключевого поля (обычно `id`).
     * @param array<int,string>  $ids      Уникальные ID; пустой массив допустим.
     * @param array<int,string>  $columns  Список столбцов; по умолчанию `*`.
     * @pre $table!==''; $pk!==''; каждый $ids[i] !== ''.
     * @side-effects Чтение БД (до N запросов).
     * @return array<string,array<string,mixed>> Ассоц.массив: id => строка.
     * @throws \Throwable При ошибках БД.
     * @example $users = $svc->fetchByIds('users','id', ['u1','u2'], ['id','username']);
     */
    public function fetchByIds(
        string $table,
        string $pk,
        array $ids,
        array $columns = ['*']
    ): array {
        $out = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if ($id === '') {
                continue;
            }
            $qb = DB::table($table);
            if ($columns !== ['*']) {
                $qb = $qb->select($columns);
            }
            $row = $qb->where($pk, '=', $id)->first();
            if ($row) {
                $out[$id] = (array)$row;
            }
        }
        return $out;
    }

    /**
     * Вытащить ID пользователей из массива постов.
     *
     * @param array<int,array<string,mixed>> $posts
     * @return array<int,string>
     */
    public function pluckUserIds(array $posts): array
    {
        $ids = [];
        foreach ($posts as $p) {
            $u = (string)($p['user_id'] ?? '');
            if ($u !== '') {
                $ids[$u] = true;
            }
        }
        return \array_values(\array_keys($ids));
    }

    /**
     * Вытащить ID групп из карты пользователей.
     *
     * @param array<string,array<string,mixed>> $users
     * @return array<int,int>
     */
    public function pluckGroupIds(array $users): array
    {
        $ids = [];
        foreach ($users as $u) {
            $g = (int)($u['group_id'] ?? 0);
            if ($g > 0) {
                $ids[$g] = true;
            }
        }
        return \array_values(\array_keys($ids));
    }

    /**
     * Подсчитать число постов по авторам (на входе — массив постов).
     *
     * @param array<int,array<string,mixed>> $posts
     * @return array<string,int>
     */
    public function countPostsByUserFromArray(array $posts): array
    {
        $map = [];
        foreach ($posts as $p) {
            $uid = (string)($p['user_id'] ?? '');
            if ($uid !== '') {
                $map[$uid] = ($map[$uid] ?? 0) + 1;
            }
        }
        return $map;
    }

    /**
     * Теги темы (taggables → tags) в порядке по названию.
     *
     * @param string $topicId UUID темы.
     * @pre $topicId !== ''.
     * @side-effects Чтение БД.
     * @return array<int,array{slug:string,title:string,color:string,is_active:int}>
     */
    public function listTagsForTopic(string $topicId): array
    {
        $rows = DB::table('taggables AS tg')
            ->join('tags AS t', 't.id', '=', 'tg.tag_id')
            ->select(['t.slug', 't.title', 't.color', 't.is_active'])
            ->where('tg.entity', '=', 'topic')
            ->where('tg.entity_id', '=', $topicId)
            ->orderBy('t.title', 'ASC')
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $a = (array)$r;
            $out[] = [
                'slug'      => (string)($a['slug'] ?? ''),
                'title'     => (string)($a['title'] ?? ''),
                'color'     => (string)($a['color'] ?? ''),
                'is_active' => (int)($a['is_active'] ?? 0),
            ];
        }
        return $out;
    }
}
