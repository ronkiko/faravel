<?php // v0.4.3
/* app/Http/ViewModels/Forum/HubPageVM.php
Purpose: ViewModel for a hub/tag page. Carries normalized data from service layer into Blade views.
FIX: `fromArray()` return type changed to `static` to match ArrayBuildable; added safe property-bag
     (__get/__isset) and pagination normalization.
*/
namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * Hub (tag) page ViewModel.
 * Keeps the controller thin: the service prepares data; VM defines the shape for the View layer.
 */
final class HubPageVM implements ArrayBuildable
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data Normalized data for the View layer.
     *                                  Minimal expected keys: tag_slug, title, topics, pagination.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build HubPageVM from service-provided array.
     *
     * Preconditions:
     * - $data is an associative array.
     * - Required: tag_slug:string (non-empty).
     * - Recommended: title:?string, description:?string, topics:array, pagination:array shape.
     *
     * Side effects: None.
     *
     * @param array<string,mixed> $data
     * @return static
     *
     * @throws \InvalidArgumentException When required key tag_slug is missing or empty.
     * @example
     *  $vm = HubPageVM::fromArray([
     *    'tag_slug' => 'php',
     *    'title' => 'PHP',
     *    'topics' => []],
     *    'pagination' => ['page'=>1,'per_page'=>20,'total'=>120,'last_page'=>6],
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $tag = (string)($data['tag_slug'] ?? '');
        if ($tag === '') {
            throw new \InvalidArgumentException('HubPageVM requires non-empty tag_slug.');
        }

        // Normalize recommended fields.
        $normalized = [
            'tag_slug'    => $tag,
            'title'       => array_key_exists('title', $data) ? (string)$data['title'] : $tag,
            'description' => array_key_exists('description', $data)
                ? (string)$data['description']
                : null,
            'topics'      => is_array($data['topics'] ?? null) ? (array)$data['topics'] : [],
            'pagination'  => self::normalizePagination($data['pagination'] ?? []),
            'meta'        => is_array($data['meta'] ?? null) ? (array)$data['meta'] : [],
        ];

        // Preserve extra keys for forward/backward compatibility with templates.
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
     * Get the underlying data as an array for View consumption.
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
