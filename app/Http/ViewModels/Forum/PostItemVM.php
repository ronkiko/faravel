<?php // v0.4.3
/* app/Http/ViewModels/Forum/PostItemVM.php
Purpose: ViewModel for a single post within a topic. Flat, safe shape for rendering in Blade.
FIX: `fromArray()` return type changed to `static`; added property-bag and normalization of key
     fields (id, user, content, created_at, etc.).
*/
namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * Post ViewModel.
 * Data must be prepared in the service layer — no SQL or repositories here.
 */
final class PostItemVM implements ArrayBuildable
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param array<string,mixed> $data Prepared post/user fields for safe rendering.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build PostItemVM from service-provided array.
     *
     * Preconditions:
     * - $data['id'] is a non-empty string (UUID/ULID/string PK).
     * - $data['content'] is a non-empty string, already sanitized if needed.
     *
     * Side effects: None.
     *
     * @param array<string,mixed> $data
     * @return static
     *
     * @throws \InvalidArgumentException When `id` or `content` is missing/empty.
     * @example
     *  $vm = PostItemVM::fromArray([
     *    'id' => '6e7c…',
     *    'topic_id' => '281e…',
     *    'user' => ['id'=>'u1','username'=>'alice','name'=>'Alice'],
     *    'content' => "Hello",
     *    'created_at' => '2025-08-25 12:00:00',
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $id      = (string)($data['id'] ?? '');
        $content = isset($data['content']) ? (string)$data['content'] : '';

        if ($id === '') {
            throw new \InvalidArgumentException('PostItemVM requires non-empty id.');
        }
        if ($content === '') {
            throw new \InvalidArgumentException('PostItemVM requires non-empty content.');
        }

        $normalized = [
            'id'         => $id,
            'topic_id'   => isset($data['topic_id']) ? (string)$data['topic_id'] : '',
            'user_id'    => isset($data['user_id']) ? (string)$data['user_id'] : '',
            'user'       => is_array($data['user'] ?? null) ? (array)$data['user'] : [],
            'content'    => $content,
            'created_at' => isset($data['created_at']) ? (string)$data['created_at'] : null,
            'updated_at' => isset($data['updated_at']) ? (string)$data['updated_at'] : null,
            'meta'       => is_array($data['meta'] ?? null) ? (array)$data['meta'] : [],
        ];

        // Preserve any extra keys (for forward/backward compatibility with templates).
        foreach ($data as $k => $v) {
            if (!array_key_exists($k, $normalized)) {
                $normalized[$k] = $v;
            }
        }

        return new static($normalized);
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
     * Flexible access to additional fields injected by services.
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
