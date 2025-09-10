<!-- v0.3.62 (admin perks index: toolbar respects perm.can_manage; без функциональных изменений) -->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_ver('/style/ui-box.css'), ENT_QUOTES, 'UTF-8') ?>">
@endpush

@section('content')
  <div class="admin perks">
    <h1 class="page-title">Perks</h1>

    <details class="info-details">
      <summary>Что такое Perks?</summary>
      <div class="info-details__body">
        <p><strong>Perks</strong> — косметические «плюшки», которые становятся доступны начиная с заданной группы пользователя (<code>min_group_id</code>).</p>
        <ul>
          <li>Примеры: цветной ник, декоративная рамка сообщений, подпись в профиле.</li>
          <li>Доступность: перк виден/активируется, если <code>user.group_id ≥ min_group_id</code>.</li>
          <li>Кэш: обновляется автоматически после создания/редактирования перков.</li>
        </ul>
      </div>
    </details>

    <?php if (!empty($flash['success'])): ?>
      <div class="alert success"><?= htmlspecialchars($flash['success'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
      <div class="alert error"><?= htmlspecialchars($flash['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="toolbar toolbar--compact" style="margin-bottom:10px;">
      <a class="button button--ghost" href="/admin" title="Назад">
        <span class="icon">←</span><span>Назад</span>
      </a>
      <?php if (!empty($perm['can_manage'])): ?>
        <a class="button" href="/admin/perks/new" title="Создать perk">
          <span class="icon">✚</span><span>Новый perk</span>
        </a>
      <?php endif; ?>
    </div>

    <div class="card table-card">
      <div class="table-wrap">
        <table class="table table--striped table--hover table--compact table--sticky table--stacked-on-mobile">
          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th class="col-name">Key</th>
              <th class="col-info">Info</th>
              <th class="col-role">Min Group</th>
              <th class="col-actions">Действия</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($perks as $p): ?>
            <?php $label = trim((string)($p['label'] ?? '')); $desc = trim((string)($p['description'] ?? '')); ?>
            <tr>
              <td class="col-id"   data-th="ID"><?= (int)$p['id'] ?></td>
              <td class="col-name" data-th="Key">
                <code class="code-badge"><?= htmlspecialchars($p['key'], ENT_QUOTES, 'UTF-8') ?></code>
              </td>
              <td class="col-info" data-th="Info">
                <?php if ($label !== ''): ?>
                  <div class="info-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($desc  !== ''): ?>
                  <div class="info-desc"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($label === '' && $desc === ''): ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="col-role" data-th="Min Group">
                <span class="badge" title="group_id"><?= (int)$p['min_group_id'] ?></span>
              </td>
              <td class="col-actions" data-th="Действия">
                <div class="actions">
                  <?php if (!empty($perm['can_manage'])): ?>
                    <a class="icon-btn" href="/admin/perks/<?= (int)$p['id'] ?>/edit" title="Править">
                      <span class="icon">✏️</span>
                    </a>
                    <form method="POST" action="/admin/perks/<?= (int)$p['id'] ?>/delete" class="inline-form" onsubmit="return confirm('Удалить perk?')">
                      <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">
                        <span class="icon">🗑</span>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
