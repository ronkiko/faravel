<!-- v0.1.0 — список форумов, категории по forum_id, кнопки действий; без inline-проверок ролей -->
@extends('layouts.main_admin')

@section('admin_content')
  <?php
    $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    // Быстрый поиск названия группы по id
    $groupName = function (int $gid) use ($groups): string {
      foreach ($groups ?? [] as $g) {
        if ((int)$g['id'] === $gid) return (string)($g['name'] ?? (string)$gid);
      }
      return (string)$gid;
    };
  ?>

  <h1 class="page-title" style="margin-top:0;">Форумы</h1>
  @include('layouts.flash')

  <div class="toolbar toolbar--compact" style="margin-bottom:10px;">
    <a class="button button--ghost" href="/admin" title="Назад">
      <span class="icon" aria-hidden="true">←</span><span>Назад</span>
    </a>
    <a class="button" href="/admin/forums/new" title="Создать форум">
      <span class="icon" aria-hidden="true">✚</span><span>Новый форум</span>
    </a>
  </div>

  <div class="card table-card">
    <div class="table-wrap">
      <table class="table table--striped table--hover table--compact table--sticky table--stacked-on-mobile">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Parent</th>
            <th>Order</th>
            <th>Visible</th>
            <th>Locked</th>
            <th>Min&nbsp;Group</th>
            <th>Категории</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (($forums ?? []) as $f): ?>
          <?php
            $fid    = (string)$f['id'];
            $cats   = $catsByForum[$fid] ?? [];
            $catStr = $cats ? implode(', ', array_map($e, $cats)) : '—';
          ?>
          <tr>
            <td data-th="Title"><?= $e($f['title']) ?></td>
            <td data-th="Slug"><span class="muted"><?= $e($f['slug']) ?></span></td>
            <td data-th="Parent"><?= $e($f['parent_title'] ?? '—') ?></td>
            <td data-th="Order"><?= $e($f['order_id'] ?? '') ?></td>
            <td data-th="Visible"><?= (int)($f['is_visible'] ?? 0) ? 'yes' : 'no' ?></td>
            <td data-th="Locked"><?= (int)($f['is_locked'] ?? 0) ? 'yes' : 'no' ?></td>
            <td data-th="Min Group"><span class="badge"><?= $e($groupName((int)($f['min_group'] ?? 0))) ?></span></td>
            <td data-th="Категории"><?= $catStr ?></td>
            <td data-th="Действия">
              <div class="actions">
                <a class="icon-btn" href="/admin/forums/<?= $e($fid) ?>/edit" title="Править">
                  <span class="icon" aria-hidden="true">✏️</span>
                </a>
                <form method="POST" action="/admin/forums/<?= $e($fid) ?>/delete" class="inline-form"
                      onsubmit="return confirm('Удалить форум? Дочерние должны быть удалены/перенесены.')">
                  <?= csrf_field() ?>
                  <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">
                    <span class="icon" aria-hidden="true">🗑</span>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($forums)): ?>
          <tr><td colspan="9" class="muted" style="padding:8px;">Форумов пока нет.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
@endsection
