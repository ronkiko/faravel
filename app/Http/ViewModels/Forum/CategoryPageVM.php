<?php // v0.4.3
/* app/Http/ViewModels/Forum/CategoryPageVM.php
Purpose: ViewModel страницы категории: нормализует данные под строгий Blade (флаги, список
         хабов) и предоставляет meta (title, breadcrumbs) без логики во Blade.
FIX: Добавлены meta.has_breadcrumbs и флаги у элементов крошек (sep_before, has_url) для
     строгого рендера в layouts.theme без @php/@endphp.
*/
namespace App\Http\ViewModels\Forum;

final class CategoryPageVM
{
    /** @var array<string,mixed> */
    private array $data = [];

    /**
     * Фабрика VM из структур контроллера/сервиса.
     *
     * Ожидает:
     * - category: array{id?:string,slug?:string,title?:string,description?:string}
     * - hubs: array<int,array{slug:string,title:string,color?:string,is_active?:int,position?:int|null}>
     *
     * @param array<string,mixed> $in
     * @return self
     */
    public static function fromArray(array $in): self
    {
        $vm = new self();

        $cat      = (array)($in['category'] ?? []);
        $slug     = (string)($cat['slug'] ?? '');
        $title    = (string)($cat['title'] ?? $slug);
        $desc     = (string)($cat['description'] ?? '');
        $descTrim = trim($desc);

        $hubsIn = (array)($in['hubs'] ?? []);
        $hubs   = [];
        foreach ($hubsIn as $h) {
            $a      = (array)$h;
            $hSlug  = (string)($a['slug'] ?? '');
            $active = (int)($a['is_active'] ?? 1);
            $hubs[] = [
                'title'     => (string)($a['title'] ?? $hSlug),
                'url'       => '/forum/f/' . $hSlug . '/',
                'css_class' => 'f-hub' . ($active ? '' : ' f-hub--muted'),
            ];
        }

        $vm->data = [
            'meta' => [
                'title'           => 'Категория: ' . $title,
                'has_breadcrumbs' => 1,
                'breadcrumbs'     => [
                    ['label' => 'Форум', 'url' => '/forum/', 'has_url' => 1, 'sep_before' => 0],
                    ['label' => $title,  'url' => '',        'has_url' => 0, 'sep_before' => 1],
                ],
            ],
            'category' => [
                'slug'            => $slug,
                'title'           => $title,
                'description'     => $desc,
                'has_description' => ($descTrim !== '') ? 1 : 0,
            ],
            'has_hubs' => !empty($hubs) ? 1 : 0,
            'hubs'     => $hubs,
        ];

        return $vm;
    }

    /**
     * Отдать данные для Blade.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
