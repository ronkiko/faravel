<?php // v0.4.3
/* app/Http/ViewModels/Forum/CategoryPageVM.php
Purpose: ViewModel for a category page (category header + list of forums/topics + pagination).
FIX: `fromArray()` return type changed to `static`; added safe property-bag and normalization of
     common fields (category, items, pagination).
*/
namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * Category page ViewModel.
 * Pure data container for Views; no business logic or DB queries here.
 */
final class CategoryPageVM implements ArrayBuildable
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data Prepared category and items data.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build CategoryPageVM from service-provided array.
     *
     * Preconditions:
     * - $data['category'] is an array with at least id:string and title:string.
     * - $data['items'] is an array (forums or topics prepared by service).
     *
     * Side effects: None.
     *
     * @param array<string,mixed> $data
     * @return static
     *
     * @throws \InvalidArgumentException When category is missing or invalid.
     * @example
     *  $vm = CategoryPageVM::fromArray([
     *    'category' => ['id'=>'c1','slug'=>'general','title'=>'General'],
     *    'items' => [],
     *    'pagination' => ['page'=>1,'per_page'=>20,'total'=>12,'last_page'=>1],
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $category = is_array($data['category'] ?? null) ? (array)$data['category'] : null;
        if (!$category || ($category['id'] ?? '') === '' || ($category['title'] ?? '') === '') {
            throw new \InvalidArgumentException('CategoryPageVM requires valid category data.');
        }

        $normalized = [
            'category'   => $category,
            'items'      => is_array($data['items'] ?? null) ? (array)$data['items'] : [],
            'pagination' => self::normalizePagination($data['pagination'] ?? []),
            'meta'       => is_array($data['meta'] ?? null) ? (array)$data['meta'] : [],
        ];

        // Preserve extras.
        foreach ($data as $k => $v) {
            if (!array_key_exists($k, $normalized)) {
                $normalized[$k] = $v;
            }
        }

        return new static($normalized);
    }

    /**
     * Normalize pagination into a fixed array shape.
     *
     * @param array<string,mixed> $p
     * @return array{page:int,per_page:int,total:int,last_page:int}
     */
    private static function normalizePagination(array $p): array
    {
        $page     = max(1, (int)($p['page'] ?? 1));
        $perPage  = max(1, (int)($p['per_page'] ?? 20));
        $total    = max(0, (int)($p['total'] ?? 0));
        $lastPage = (int)max(1, $p['last_page'] ?? (int)ceil($total / max(1, $perPage)));

        return [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Expose underlying data as array.
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Safe property-bag access for flexible templates.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }
}
