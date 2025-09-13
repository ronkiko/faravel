# docs/STYLEGUIDE_HTTP.md

# Стайлгайд HTTP-ответов в Faravel  
Единый «велосипед»: `response()->view(...)`

## Зачем
Раньше в проекте существовали два способа вернуть HTML:
1) через фабрику — `response()->view('tpl', $data, $status, $headers)`;
2) через метод на объекте — `(new Response())->view('tpl', ...)`.

Два пути = риск рассинхронизации и ошибок типов (в т.ч. 500 на главной).  
Мы стандартизируемся на **одном способе** — как в Laravel.

---

## TL;DR (обязательно к исполнению)

- ✅ Используйте **только** `response()->view('tpl', $data, 200, $headers)`.  
- 🚫 Не используйте `(new Response())->view(...)` — **устарело**.  
- ❌ Не возвращайте «сырые» объекты View/Blade из контроллера.  
- ❌ Не делайте `echo` / вывод из сервиса/VM — рендер только на уровне контроллера.

---

## Каноничный паттерн (строгий MVC)

**Controller → Service → ViewModel → View → Response**

### Controller (тонкий)

// Controller: return HTML via factory
// Comments: English only
public function __invoke(Request $req): Response {
    $vm = $this->service->home(); // returns HomeVM
    return response()->view('home.index', ['vm' => $vm], 200, [
        'X-Frame-Options' => 'SAMEORIGIN',
    ]);
}

### Service (бизнес-логика)

// Service: no rendering here, just domain logic
public function home(): HomeVM {
    $topics = $this->repo->latestTopics(20);
    return new HomeVM($topics);
}


### ViewModel (данные для представления)

// ViewModel: shaping data for template
final class HomeVM {
    /** @var array<int,TopicDTO> */
    public array $topics;
    public function __construct(array $topics) { $this->topics = $topics; }
}

View (Blade/шаблон) — только отображение
{{-- resources/views/home/index.blade.php --}}
{{-- View: presentation only, no business logic --}}
<h1>Welcome</h1>
<ul>
@foreach ($vm->topics as $t)
  <li>{{ $t->title }}</li>
@endforeach
</ul>

### Частые вопросы

Q: А если мне нужен нестандартный статус/заголовки?
A: Укажи в фабрике:
return response()->view('home.index', $data, 201, ['X-Foo' => 'bar']);

Q: Можно вернуть View напрямую, а фреймворк сам приведёт к строке?
A: Нет. В Faravel контент ответа должен быть строкой. Рендер делается фабрикой.

Q: Чем плохо (new Response())->view()?
A: Дублирует логику, повышает шанс рассинхрона, ломает инварианты Response.