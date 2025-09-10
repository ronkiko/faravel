<!-- doc/rendering.md -->
# Рендеринг страниц в Faravel (тонкие контроллеры → сервисы → VM → Blade)

## Поток запроса
1. Роутер вызывает экшен (тонкий контроллер).
2. Контроллер дергает сервисы домена, получает данные и строит **Page VM**.
3. Контроллер вызывает **LayoutService** → получает **LayoutVM** (гарантированные поля).
4. Возврат `response()->view('view.name', ['vm'=>..., 'layout'=>...])`.
5. **BladeEngine**:
   - разворачивает `@extends/@section/@yield/@push/@stack` и `@include`;
   - компилирует в PHP (строгий режим);
   - рендерит с подстановкой значений из `vm` и `layout`.

## Строгий Blade
- В `{{ ... }}`: только переменные/свойства/индексы (без вызовов функций).
- Управляющие конструкции: `@if/@elseif/@else/@endif`, `@foreach/@endforeach`, `@for`, `@while`.
- Сырые `@php`, `<?php` запрещены.

## LayoutVM (обязательные поля)
- `layout.locale` — строка, не пустая.
- `layout.title` — заголовок страницы.
- `layout.brand` — бренд/подвал.
- `layout.nav` — ссылки и флаги авторизации.

## Расширяемые поля
- `layout.meta`, `layout.assets`, `layout.breadcrumbs`, `layout.page`, `layout.theme` и др.
Добавляются через `LayoutService` и валидируются/нормализуются в `LayoutVM`.
