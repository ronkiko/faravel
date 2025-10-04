<?php // v0.4.123
/* app/Http/ViewModels/Forum/ForumIndexPageVM.php
Purpose: ViewModel главной страницы форума для строгого Blade. Отдаёт ровно те
         ключи, которые ждёт шаблон: title, has_categories, categories[{...}],
         page, perPage, total. Никаких побочных эффектов на уровне файла.
FIX: Полная нормализация класса: корректный namespace/FQCN, без top-level кода,
     статический fromArray(array): static, строгая валидация, чистый toArray(): array.
*/
namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;
use App\Contracts\ViewModel\ViewModelContract;

final class ForumIndexPageVM implements ViewModelContract, ArrayBuildable
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
     * Поддерживаются ключи:
     *  - title?:string
     *  - categories?: array<int,array{
     *      id:string,slug:string,title:string,description?:string
     *    }>
     *  - page?:int, perPage?:int, total?:int
     *
     * @pre Для каждой категории заданы id, slug, title.
     * @side-effects Нет.
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
        $src = isset($data['categories']) && is_array($data['categories'])
            ? $data['categories']
            : [];

        $normalized = [];
        foreach ($src as $i => $row) {
            $a = (array) $row;

            $id    = (string) ($a['id'] ?? '');
            $slug  = (string) ($a['slug'] ?? '');
            $title = (string) ($a['title'] ?? '');

            if ($id === '' || $slug === '' || $title === '') {
                throw new \InvalidArgumentException(
                    "ForumIndexPageVM.categories[{$i}] requires id, slug, title"
                );
            }

            $desc = (string) ($a['description'] ?? '');
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
            $vm->page = max(1, (int) $data['page']);
        }
        if (isset($data['perPage'])) {
            $vm->perPage = max(1, (int) $data['perPage']);
        }
        if (isset($data['total'])) {
            $vm->total = max(0, (int) $data['total']);
        }

        return $vm;
    }

    /**
     * Представление для Blade.
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
