# Спецификация Blade для Faravel v0.4.140

## 1. Общие правила
- Шаблоны только Blade. Inline PHP запрещён.
- Разрешён только экранированный вывод `{{ ... }}`.
- `{!! ... !!}` запрещён.
- `@php ... @endphp` запрещён.
- HTML- и Blade-комментарии удаляются на этапе компиляции.
- Пустые строки схлопываются.

## 2. Белый список директив

### 2.1 Макеты и секции
- `@extends('view.name')`
- `@section('name') ... @endsection`
- `@yield('name')`
- `@push('stack') ... @endpush`
- `@stack('stack')`
- `@include('view.name', {опц. массив})`

**Точно**
- Имена представлений — в кавычках.
- `@include` рендерит через `ViewFactory::make()` и делает `trim()` результата.

### 2.2 Условия
- `@if( (УСЛОВИЕ) ) ... @endif`
- `@elseif( (УСЛОВИЕ) )`
- `@else`

Поддерживаются спец-формы (компилируются явно):
- `@if(!empty( (ВЫРАЖЕНИЕ) ))`
- `@elseif(!empty( (ВЫРАЖЕНИЕ) ))`
- `@if(isset( (ВЫРАЖЕНИЕ) ))`
- `@elseif(isset( (ВЫРАЖЕНИЕ) ))`

**Требование**
- Внутри `@if(...)` и `@elseif(...)` одна внешняя парная скобочная группа.

### 2.3 Циклы
- `@foreach( (ВЫРАЖЕНИЕ) ) ... @endforeach`
- `@for( (ВЫРАЖЕНИЕ) ) ... @endfor`
- `@while( (ВЫРАЖЕНИЕ) ) ... @endwhile`

## 3. Вывод данных

### 3.1 Эхо
- `{{ EXPR }}` всегда экранирует:  
  `htmlspecialchars((string)(is_array(EXPR)?'':EXPR), ENT_QUOTES, 'UTF-8')`.

### 3.2 Dot-нотация
- Внутри `{{ ... }}` `$a.b.c` → `$a['b']['c']` для простых идентификаторов.
- Применяется только к переменным с точками; остальной код не меняется.

## 4. Запрещено
- Inline PHP: `<?php ... ?>`, `<?= ... ?>`, `<?=` — ошибка компиляции.
- `@php ... @endphp` — ошибка.
- Сырой вывод `{!! ... !!}` — ошибка в строгом режиме.
- Пользовательские директивы, возвращающие PHP-код. Разрешены только те, что
  возвращают HTML/Blade/`{{ ... }}`.

## 5. Зарезервированные имена
Нельзя регистрировать кастомные директивы с именами:  
`if, elseif, else, endif, foreach, endforeach, for, endfor, while, endwhile, extends, section, endsection, yield, show, append, overwrite, push, endpush, stack, include, each`.

## 6. Точное поведение компиляции

### 6.1 Условия
- `@if(!empty($vm['topics']))` → `<?php if (!empty ($vm['topics'])) { ?>`
- `@if(isset($t['posts']))` → `<?php if (isset ($t['posts'])) { ?>`
- Общие формы:
  - `@if( (COND) )` → `<?php if ( (COND) ) { ?>`
  - `@elseif( (COND) )` → `<?php } elseif ( (COND) ) { ?>`
  - `@else` → `<?php } else { ?>`
  - `@endif` → `<?php } ?>`

### 6.2 Циклы
- `@foreach( ($vm['topics'] as $t) ) ... @endforeach`  
  → `<?php foreach (($vm['topics'] as $t)) { ?> ... <?php } ?>`

### 6.3 Эхо
- `{{ $t['title'] }}` →  
  `<?= htmlspecialchars((string)(is_array(($t['title'])) ? '' : ($t['title'])), ENT_QUOTES, 'UTF-8') ?>`

## 7. Поведение кеша
- Скомпилированный PHP кешируется.
- Ключ кеша = hash(path|mtime|size|COMPILE_SIG|flags) или hash(inline|md5(source)|COMPILE_SIG|flags).
- Обновление `COMPILE_SIG` инвалидирует кеш автоматически.

## 8. Примеры

### 8.1 Хаб
```blade
@extends('layouts.theme')

@section('content')
  <h1>{{ $vm['title'] }}</h1>

  @if(!empty($vm['topics']))
    @foreach(($vm['topics'] as $t))
      <a href="{{ $t['url'] }}">{{ $t['title'] }}</a>
    @endforeach
  @else
    Нет тем
  @endif

  @if($vm['pager']['has_pages'])
    <nav>
      @if($vm['pager']['has_prev'])
        <a href="{{ $vm['links']['prev'] }}">Назад</a>
      @endif
      @if($vm['pager']['has_next'])
        <a href="{{ $vm['links']['next'] }}">Вперёд</a>
      @endif
    </nav>
  @endif
@endsection
