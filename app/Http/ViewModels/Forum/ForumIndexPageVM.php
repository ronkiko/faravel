<?php // v0.4.122
/* app/Http/ViewModels/Forum/ForumIndexPageVM.php
Purpose: ViewModel главной страницы форума для строгого Blade. Отдаёт ровно те
         ключи, которые ждёт шаблон: title, has_categories, categories[{...}].
FIX: Добавлен 'has_categories'; ссылки категорий нормализованы к /forum/c/{slug}/.
*/
namespace App\Http\ViewModels\Forum;

final class ForumIndexPageVM
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
     * Сборка VM из данных сервиса/репозитория.
     *
     * @param array<string,mixed> $data
     *   Поддерживаются ключи:
     *   - title?:string
     *   - categories?:array<int,array{id:string,slug:string,title:string,description?:string}>
     *   - forums?:array<int,array{id:string,slug:string,title:string,description?:string}> (legacy)
     *   - page?:int, perPage?:int, total?:int
     * Preconditions:
     *  - id/slug/title должны быть заданы для каждой категории.
     * Side effects: нет.
     * @return static
     * @throws \InvalidArgumentException При отсутствии id|slug|title.
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
            $src = $data['forums']; // legacy
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

            $normalized[] = [
                'id'          => $id,
                'slug'        => $slug,
                'title'       => $title,
                'description' => $desc,
                'url'         => '/forum/c/' . rawurlencode($slug) . '/',
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
     * Отдать массив для строгого Blade (никаких вычислений в шаблоне).
     *
     * @return array{
     *   title:string,
     *   has_categories:bool,
     *   categories:array<int,array{
     *     id:string,slug:string,title:string,description:string,url:string
     *   }>,
     *   page:int, perPage:int, total:int
     * }
     */
    public function toArray(): array
    {
        return [
            'title'          => $this->title,
            'has_categories' => count($this->categories) > 0,
            'categories'     => $this->categories,
            'page'           => $this->page,
            'perPage'        => $this->perPage,
            'total'          => $this->total,
        ];
    }
}
