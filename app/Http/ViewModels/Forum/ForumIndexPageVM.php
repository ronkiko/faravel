<?php // v0.4.2
/* app/Http/ViewModels/Forum/ForumIndexPageVM.php
Purpose: 1) Готовит презентационные данные для /forum/: заголовок, категории, пагинацию.
         2) Инкапсулирует сборку ссылок (url) для категорий, чтобы Blade шаблон был
            «тупым» и не конкатенировал строки — это устраняет синтаксическую ошибку.
FIX: Добавлена нормализация categories (id, slug, title, description, url); toArray()
     теперь отдаёт ключ 'categories'. Принят legacy-ключ 'forums' на вход.
*/

namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * ForumIndexPageVM: presentation-only data for forum index.
 * Controller → Service → VM → View. Никакой бизнес-логики.
 */
final class ForumIndexPageVM implements ArrayBuildable
{
    /** @var string */
    private string $title = 'Форум';

    /**
     * @var array<int,array{
     *   id:string, slug:string, title:string, description:string, url:string
     * }>
     */
    private array $categories = [];

    /** @var int */
    private int $page = 1;

    /** @var int */
    private int $perPage = 20;

    /** @var int */
    private int $total = 0;

    /**
     * Построить VM из массива, который вернул сервис.
     *
     * Preconditions:
     * - $data['categories'] ИЛИ $data['forums'] — массив элементов с ключами
     *   id, slug, title, description?.
     * - Допустимы: title (string), page (int>=1), perPage (int>=1), total (int>=0).
     *
     * Side effects: нет.
     *
     * @param array<string,mixed> $data
     * @return static
     *
     * @throws \InvalidArgumentException если отсутствуют id|slug|title у элемента.
     *
     * @example
     *  ForumIndexPageVM::fromArray([
     *    'title' => 'Форум',
     *    'categories' => [
     *      ['id'=>'1','slug'=>'general','title'=>'General','description'=>'...'],
     *    ],
     *    'page' => 1, 'perPage' => 20, 'total' => 1
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $vm = new static();

        if (isset($data['title']) && is_string($data['title'])) {
            $vm->title = $data['title'];
        }

        /** @var array<int, array<string,mixed>> $src */
        if (isset($data['categories']) && is_array($data['categories'])) {
            $src = $data['categories'];
        } elseif (isset($data['forums']) && is_array($data['forums'])) {
            // Legacy support: старый ключ 'forums'
            $src = $data['forums'];
        } else {
            $src = [];
        }

        $normalized = [];
        foreach ($src as $row) {
            $a = (array)$row;

            $id    = (string)($a['id'] ?? '');
            $slug  = (string)($a['slug'] ?? '');
            $title = (string)($a['title'] ?? '');
            $desc  = (string)($a['description'] ?? '');

            if ($id === '' || $slug === '' || $title === '') {
                throw new \InvalidArgumentException(
                    'ForumIndexPageVM: each category requires id, slug, title.'
                );
            }

            // VM готовит презентационный URL — View его только печатает.
            $url = '/forum/c/' . rawurlencode($slug) . '/';

            $normalized[] = [
                'id'          => $id,
                'slug'        => $slug,
                'title'       => $title,
                'description' => $desc,
                'url'         => $url,
            ];
        }
        $vm->categories = $normalized;

        if (isset($data['page'])) {
            $vm->page = max(1, (int)$data['page']);
        }
        if (isset($data['perPage'])) {
            $vm->perPage = max(1, (int)$data['perPage']);
        }
        if (isset($data['total'])) {
            $vm->total = max(0, (int)$data['total']);
        }

        return $vm;
    }

    /**
     * Экспорт VM в плоский массив для Blade.
     *
     * @return array{
     *   title:string,
     *   categories:array<int,array{
     *     id:string,slug:string,title:string,description:string,url:string
     *   }>,
     *   page:int, perPage:int, total:int
     * }
     */
    public function toArray(): array
    {
        return [
            'title'       => $this->title,
            'categories'  => $this->categories,
            'page'        => $this->page,
            'perPage'     => $this->perPage,
            'total'       => $this->total,
        ];
    }
}
