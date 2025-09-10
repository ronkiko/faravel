<!-- v0.3.71 — добавлена карточка «Форумы» (/admin/forums) с описанием: управление форумами и привязкой к категориям -->
@extends('layouts.main_admin')

@section('admin_content')
  <?php
    $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $cards = $links ?? ($cards ?? [
      ['href' => '/admin/settings',   'title' => 'Настройки троттлинга', 'desc' => 'Ограничения по GET/POST, окно, глобальный лимит'],
      ['href' => '/admin/categories', 'title' => 'Категории',            'desc' => 'Создание и редактирование категорий форума'],
      ['href' => '/admin/forums',     'title' => 'Форумы',               'desc' => 'Создание/правка форумов, иерархия, привязка к категориям'],
      ['href' => '/admin/abilities',  'title' => 'Abilities',            'desc' => 'Управление способностями'],
      ['href' => '/admin/perks',      'title' => 'Perks',                'desc' => 'Управление перками'],
    ]);
  ?>

  <h1 class="page-title">Админ-панель</h1>

  <details class="info-details">
    <summary>Что здесь есть?</summary>
    <div class="info-details__body">
      <p>Слева — дерево разделов, справа — быстрые карточки переходов. Интерфейс унифицирован.</p>
    </div>
  </details>

  <div class="toolbar toolbar--compact" style="margin-bottom:10px;">
    <a class="button button--ghost" href="/admin" title="Обновить обзор">
      <span class="icon" aria-hidden="true">↻</span><span>Обновить</span>
    </a>
  </div>

  <div class="cards-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;">
    <?php foreach ($cards as $c): ?>
      <a class="card" href="<?= $e($c['href']) ?>" style="text-decoration:none;">
        <div style="font-weight:700;color:var(--ui-fg);margin-bottom:6px;"><?= $e($c['title']) ?></div>
        <?php if (!empty($c['desc'])): ?>
          <div class="muted" style="font-size:.95rem;"><?= $e($c['desc']) ?></div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
@endsection
