<?php // v0.4.3
/* app/Http/ViewModels/Forum/TopicPageVM.php
Purpose: ViewModel for a topic page (topic header + posts list + pagination + permissions).
FIX: `fromArray()` return type changed to `static`; added safe property-bag and normalization of
     common fields (topic, posts, pagination, abilities).
*/
namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * Topic page ViewModel.
 * Strictly a data shape for the View. No business logic or DB calls.
 */
final class TopicPageVM implements ArrayBuildable
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data Prepared topic and posts data.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build TopicPageVM from service-provided array.
     *
     * Preconditions:
     * - $data['topic'] is an array with at least id:string and title:string.
     * - $data['posts'] is an array (can contain PostItemVM arrays).
     *
     * Side effects: None.
     *
     * @param array<string,mixed> $data
     * @return static
     *
     * @throws \InvalidArgumentException When topic is missing or invalid.
     * @example
     *  $vm = TopicPageVM::fromArray([
     *    'topic' => ['id'=>'t1','slug'=>'welcome','title'=>'Welcome!'],
     *    'posts' => [],
     *    'pagination' => ['page'=>1,'per_page'=>20,'total'=>42,'last_page'=>3],
     *    'abilities' => ['can_reply'=>true],
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $topic = is_array($data['topic'] ?? null) ? (array)$data['topic'] : null;
        if (!$topic || ($topic['id'] ?? '') === '' || ($topic['title'] ?? '') === '') {
            throw new \InvalidArgumentException('TopicPageVM requires valid topic data.');
        }

        $normalized = [
            'topic'       => $topic,
            'posts'       => is_array($data['posts'] ?? null) ? (array)$data['posts'] : [],
            'pagination'  => self::normalizePagination($data['pagination'] ?? []),
            'abilities'   => is_array($data['abilities'] ?? null) ? (array)$data['abilities'] : [],
            'breadcrumbs' => is_array($data['breadcrumbs'] ?? null) ? (array)$data['breadcrumbs'] : [],
            'meta'        => is_array($data['meta'] ?? null) ? (array)$data['meta'] : [],
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
