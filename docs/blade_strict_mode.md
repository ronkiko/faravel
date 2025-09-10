<!-- doc/blade_strict_mode.md -->
# Строгий режим Blade (Faravel)

## Разрешено
- `{{ $vm['title'] }}`, `{{ $layout['brand'] }}`, `{{ $a->b['k'] }}`
- `@if`, `@foreach`, `@for`, `@while` (+ `@else/@elseif`)
- Макеты и инклюды: `@extends`, `@section/@yield`, `@push/@stack`, `@include`

## Запрещено
- `<?php ... ?>`, `@php ... @endphp`
- Вызовы функций/операторов в `{{ ... }}` (кроме допускаемых цепочек переменных)

## Layout
- Все ключи `layout.*` формируются в `LayoutService` и валидируются в `LayoutVM`.
- Шаблоны выводят готовые значения, без `??` и без тернарных выражений.
