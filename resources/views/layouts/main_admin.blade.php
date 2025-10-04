<!-- v0.4.2 -->
<!-- resources/views/layouts/main_admin.blade.php
Назначение: базовый лэйаут админ-панели (сайдбар + слот контента).
FIX: Удалён вызов функции в {{ }} (asset_ver). Ссылка на CSS теперь статическая.
-->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/ui-box.css">
@endpush

@section('content')
  <div class="layout-split" style="--ui-sticky-top: 10px;">
    <aside class="card side-nav">
      <div class="side-nav__title">Администрирование</div>

      <nav class="side-nav__list" aria-label="Admin sections">
        <div class="section-head" style="border-radius:8px; margin:8px 0 4px;">
          <h2 class="section-title" style="font-size:1rem;">Панель</h2>
        </div>
        <a class="side-nav__link" href="/admin"><span class="icon" aria-hidden="true">›</span><span>Обзор</span></a>
        <a class="side-nav__link" href="/admin/settings"><span class="icon" aria-hidden="true">›</span><span>Настройки</span></a>

        <div class="section-head" style="border-radius:8px; margin:10px 0 4px;">
          <h2 class="section-title" style="font-size:1rem;">Контент</h2>
        </div>
        <a class="side-nav__link" href="/admin/categories"><span class="icon" aria-hidden="true">›</span><span>Категории</span></a>
        <a class="side-nav__link" href="/admin/forums"><span class="icon" aria-hidden="true">›</span><span>Форумы</span></a>

        <div class="section-head" style="border-radius:8px; margin:10px 0 4px;">
          <h2 class="section-title" style="font-size:1rem;">Доступ</h2>
        </div>
        <a class="side-nav__link" href="/admin/abilities"><span class="icon" aria-hidden="true">›</span><span>Abilities</span></a>
        <a class="side-nav__link" href="/admin/perks"><span class="icon" aria-hidden="true">›</span><span>Perks</span></a>
      </nav>
    </aside>

    <section class="admin-main">
      @yield('admin_content')
    </section>
  </div>
@endsection
