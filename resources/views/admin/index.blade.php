<!-- v0.4.5 -->
<!-- resources/views/admin/index.blade.php
Назначение: главная страница админ-панели. Показывает статический набор разделов.
-->
@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title">Админ-панель</h1>
  <p class="muted" style="margin:6px 0 16px;">
    Управляйте разделами форума, доступами и ограничениями.
  </p>

  <div class="cards-grid"
       style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
    <a class="card" href="/admin/settings" style="text-decoration:none;">
      <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;">Настройки</div>
      <div class="muted" style="font-size:.95rem;">
        Троттлинг форм, окно ограничений, глобальные лимиты
      </div>
    </a>

    <a class="card" href="/admin/categories" style="text-decoration:none;">
      <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;">Категории</div>
      <div class="muted" style="font-size:.95rem;">
        Создание и редактирование категорий форума
      </div>
    </a>

    <a class="card" href="/admin/forums" style="text-decoration:none;">
      <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;">Форумы</div>
      <div class="muted" style="font-size:.95rem;">
        Иерархия и привязка форумов к категориям
      </div>
    </a>

    <a class="card" href="/admin/abilities" style="text-decoration:none;">
      <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;">Abilities</div>
      <div class="muted" style="font-size:.95rem;">
        Управление способностями
      </div>
    </a>

    <a class="card" href="/admin/perks" style="text-decoration:none;">
      <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;">Perks</div>
      <div class="muted" style="font-size:.95rem;">
        Управление перками
      </div>
    </a>
  </div>
@endsection
