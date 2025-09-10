<?php // v0.4.1
/* app/Http/ViewModels/Forum/CreateTopicPageVM.php
Purpose: ViewModel страницы создания темы: хранит заголовок, контекст категории,
         данные формы и ошибки для безопасной передачи во вьюху.
FIX: Совместимость с контрактом ArrayBuildable — fromArray() теперь возвращает `static`
     (а не конкретный класс), приведены типы и безопасные значения по умолчанию.
*/

namespace App\Http\ViewModels\Forum;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * CreateTopicPageVM — презентационный контейнер данных для страницы "создать тему".
 * MVC: Controller → Service → VM → View. Логика отсутствует, только нормализация данных.
 */
final class CreateTopicPageVM implements ArrayBuildable
{
    /** @var string Заголовок страницы. */
    public string $title = 'Создать тему';

    /**
     * @var array<string,mixed> Контекст категории (ожидаемые ключи: slug, name, description).
     */
    public array $category = [];

    /**
     * @var array{
     *   title:string,
     *   body:string,
     *   tags:array<int,string>
     * } Данные формы (готовые для {{ }} без вызовов функций).
     */
    public array $form = [
        'title' => '',
        'body'  => '',
        'tags'  => [],
    ];

    /** @var array<string, array<int,string>> Ошибки валидации по полям. */
    public array $errors = [];

    /** @var null|string URL для "назад". */
    public ?string $backUrl = null;

    /**
     * Построение VM из массива сервисного слоя.
     *
     * Предусловия:
     *  - $data — ассоциативный массив; неизвестные ключи игнорируются.
     *  - Для формы допускаются только строки и массив строк для tags.
     * Побочные эффекты: нет.
     *
     * @param array<string,mixed> $data
     * @return static
     * @example
     *  $vm = CreateTopicPageVM::fromArray([
     *      'title' => 'Новая тема',
     *      'category' => ['slug'=>'php','name'=>'PHP'],
     *      'form' => ['title'=>'...', 'body'=>'...', 'tags'=>['laravel','faravel']],
     *      'errors' => ['title'=>['Обязательно']],
     *      'backUrl' => '/forum/c/php',
     *  ]);
     */
    public static function fromArray(array $data): static
    {
        $vm = new static();

        if (isset($data['title']) && is_string($data['title'])) {
            $vm->title = $data['title'];
        }

        if (isset($data['category']) && is_array($data['category'])) {
            /** @var array<string,mixed> $cat */
            $cat = $data['category'];
            $vm->category = $cat;
        }

        if (isset($data['form']) && is_array($data['form'])) {
            $f = $data['form'];

            $title = isset($f['title']) && is_string($f['title']) ? $f['title'] : $vm->form['title'];
            $body  = isset($f['body'])  && is_string($f['body'])  ? $f['body']  : $vm->form['body'];

            $tags = $vm->form['tags'];
            if (isset($f['tags']) && is_array($f['tags'])) {
                $tags = [];
                foreach ($f['tags'] as $t) {
                    if (is_string($t) && $t !== '') {
                        $tags[] = $t;
                    }
                }
            }

            $vm->form = ['title' => $title, 'body' => $body, 'tags' => $tags];
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            /** @var array<string, array<int,string>> $errs */
            $errs = $data['errors'];
            $vm->errors = $errs;
        }

        if (array_key_exists('backUrl', $data) && (is_string($data['backUrl']) || $data['backUrl'] === null)) {
            /** @var null|string $bu */
            $bu = $data['backUrl'];
            $vm->backUrl = $bu;
        }

        return $vm;
    }

    /**
     * Экспорт VM в массив для передачи в Blade.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'title'    => $this->title,
            'category' => $this->category,
            'form'     => $this->form,
            'errors'   => $this->errors,
            'backUrl'  => $this->backUrl,
        ];
    }
}
