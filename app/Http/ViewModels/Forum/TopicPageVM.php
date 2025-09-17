<?php // v0.4.5
/* app/Http/ViewModels/Forum/TopicPageVM.php
Purpose: ViewModel for a topic page (topic header + posts list + pagination + permissions).
FIX: Canonical meta ('meta.can_reply', 'meta.reply_url') are filled and legacy aliases are
     mirrored: top-level 'can_reply' and 'links.reply'. Posts normalized to arrays.
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
     * Канонизирует ключи ответа, сохраняя обратную совместимость шаблонов.
     *
     * Preconditions:
     * - $data['topic'] is array with at least id:string and title:string.
     * - $data['posts'] is array (can contain PostItemVM arrays/objects).
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
     *    'meta' => ['reply_url'=>'/forum/t/welcome/reply']
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $topic = \is_array($data['topic'] ?? null) ? (array) $data['topic'] : null;
        if (!$topic || ($topic['id'] ?? '') === '' || ($topic['title'] ?? '') === '') {
            throw new \InvalidArgumentException('TopicPageVM requires valid topic data.');
        }

        $abilities = \is_array($data['abilities'] ?? null) ? (array) $data['abilities'] : [];
        $metaIn    = \is_array($data['meta'] ?? null) ? (array) $data['meta'] : [];
        $linksIn   = \is_array($data['links'] ?? null) ? (array) $data['links'] : [];

        // Legacy aliases → canonical meta.*
        $canReply = self::pickBool(
            $metaIn['can_reply'] ?? null,
            $abilities['can_reply'] ?? null,
            $data['can_reply'] ?? null,
            $data['canReply'] ?? null
        );

        $replyUrl = self::pickString(
            $metaIn['reply_url'] ?? null,
            $linksIn['reply'] ?? null,
            $data['reply_url'] ?? null,
            $data['replyUrl'] ?? null
        );

        // Build normalized tree.
        $normalized = [
            'topic'       => [
                'id'             => (string) ($topic['id'] ?? ''),
                'slug'           => (string) ($topic['slug'] ?? ''),
                'title'          => (string) ($topic['title'] ?? ''),
                'category_slug'  => (string) ($topic['category_slug'] ?? ($data['category_slug'] ?? '')),
                'category_title' => (string) ($topic['category_title'] ?? ($data['category_title'] ?? '')),
            ],
            'posts'       => self::normalizePosts($data['posts'] ?? []),
            'pagination'  => self::normalizePagination($data['pagination'] ?? []),
            // Keep abilities, but sync can_reply with canonical.
            'abilities'   => (static function (array $a, bool $cr): array {
                $a['can_reply'] = $cr;
                return $a;
            })($abilities, $canReply),
            'breadcrumbs' => \is_array($data['breadcrumbs'] ?? null)
                ? (array) $data['breadcrumbs'] : [],
            'meta'        => [
                'return_to' => (string) ($metaIn['return_to'] ?? ($data['return_to'] ?? '/forum')),
                'tags'      => \is_array($metaIn['tags'] ?? null) ? (array) $metaIn['tags'] : [],
                'reply_url' => $replyUrl,      // canonical
                'can_reply' => $canReply,      // canonical
            ],
            // Legacy-friendly aliases for existing blades
            'links'      => (static function (array $in, string $reply): array {
                // Ensure links['reply'] filled from canonical if missing.
                if ($reply !== '' && ($in['reply'] ?? '') === '') {
                    $in['reply'] = $reply;
                }
                return $in;
            })($linksIn, $replyUrl),
            'can_reply'  => $canReply,
        ];

        // Preserve extras without overriding normalized keys.
        foreach ($data as $k => $v) {
            if (!\array_key_exists($k, $normalized)) {
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
        $page     = \max(1, (int) ($p['page'] ?? 1));
        $perPage  = \max(1, (int) ($p['per_page'] ?? 20));
        $total    = \max(0, (int) ($p['total'] ?? 0));
        $lastPage = (int) \max(1, $p['last_page'] ?? (int) \ceil($total / \max(1, $perPage)));

        return [
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Normalize posts list to array-of-arrays for Blade.
     *
     * @param mixed $posts
     * @return array<int,array<string,mixed>>
     */
    private static function normalizePosts(mixed $posts): array
    {
        if (!\is_array($posts)) {
            return [];
        }
        $out = [];
        foreach ($posts as $p) {
            if (\is_object($p) && \method_exists($p, 'toArray')) {
                /** @var array<string,mixed> $a */
                $a = $p->toArray(); // VM or DTO object
                $out[] = $a;
            } else {
                /** @var array<string,mixed> $a */
                $a = (array) $p;
                $out[] = $a;
            }
        }
        return $out;
    }

    /**
     * Pick first boolean-like value, default false.
     *
     * @param mixed ...$candidates
     * @return bool
     */
    private static function pickBool(mixed ...$candidates): bool
    {
        foreach ($candidates as $c) {
            if (\is_bool($c)) {
                return $c;
            }
            if ($c === 1 || $c === '1' || $c === 'true') {
                return true;
            }
            if ($c === 0 || $c === '0' || $c === 'false') {
                return false;
            }
        }
        return false;
    }

    /**
     * Pick first non-empty string, default ''.
     *
     * @param mixed ...$candidates
     * @return string
     */
    private static function pickString(mixed ...$candidates): string
    {
        foreach ($candidates as $c) {
            if (\is_string($c) && $c !== '') {
                return $c;
            }
        }
        return '';
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
        return \array_key_exists($name, $this->data);
    }
}
