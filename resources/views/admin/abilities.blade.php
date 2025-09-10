<!-- resources/views/admin/abilities.blade.php -->
<!-- v0.5.9 — без дублирующего include('layouts.flash'); details/summary «Что здесь есть» -->
@extends('layouts.main_admin')

@section('admin_content')
<style>
  /* ---------- Tabs as a "book page" ---------- */
  .tabs{ margin-top:8px; }
  .tabs .tab-input{ position:absolute; left:-99999px; width:0; height:0; opacity:0; pointer-events:none; }
  .tabs .tabbar{ display:flex; gap:0px; align-items:flex-end; border-bottom:1px solid var(--ui-border,#d1d9e6); }
  .tabs .tab-label{
    position:relative; padding:9px 14px 10px; border:1px solid transparent; border-bottom:none;
    border-top-left-radius:10px; border-top-right-radius:10px;
    background:linear-gradient(#fafbfd,#f3f6fb); color:#5f6c7b; font-weight:700; cursor:pointer; user-select:none;
    transition:background .18s ease, color .18s ease, border-color .18s ease, box-shadow .18s ease;
  }
  .tabs .tab-label:hover{ color:#0f172a; background:#fff; }
  .tabs .tab-label:focus-visible{ outline:0; box-shadow:0 0 0 3px rgba(120,160,255,.28); }
  #t-index:checked  ~ .tabbar label[for="t-index"],
  #t-create:checked ~ .tabbar label[for="t-create"],
  #t-edit:checked   ~ .tabbar label[for="t-edit"]{
    background:#fff; color:#0f172a; border-color:var(--ui-border,#d1d9e6); box-shadow:0 1px 0 0 #fff; z-index:1;
  }
  .tabs .panels{ margin-top:-1px; }
  .tabpage{ border:1px solid var(--ui-border,#d1d9e6); border-radius:12px; background:#fff; padding:14px; overflow:hidden; }
  .panel{ display:none; }
  #t-index:checked  ~ .panels #p-index{ display:block; }
  #t-create:checked ~ .panels #p-create{ display:block; }
  #t-edit:checked   ~ .panels #p-edit{ display:block; }

  /* ---------- One table, CSS-only collapsible group TBODY ---------- */
  .table-card{ border:0; }
  .table-card .table-wrap{ border:0; }
  table.table thead th{ background:#f3f6fb; }
  tbody.abl-group .abl-head td{
    background:var(--ui-bg-2,#f7f9fc);
    border-top:1px solid var(--ui-border,#e4e9f2);
    border-bottom:1px dotted var(--ui-border,#e4e9f2);
    padding:8px 10px;
  }
  .grp-toggle{ position:absolute; left:-9999px; width:0; height:0; opacity:0; pointer-events:none; }
  .grp-label{ display:flex; align-items:center; gap:8px; width:100%; cursor:pointer; user-select:none; font-weight:800; color:#111827; }
  .grp-caret{ width:12px; height:12px; flex:0 0 12px; opacity:.8; background:currentColor; transition:transform .18s ease, opacity .18s ease;}
  .grp-toggle:checked + .grp-label .grp-caret{ transform:rotate(90deg); opacity:1; color:maroon}
  .grp-count{ margin-left:6px; font-size:12px; line-height:1; color:#586174; background:#eef2ff; border:1px solid #dbe5ff; border-radius:10px; padding:3px 6px; font-weight:700; }
  /* hide items when toggle is OFF — CSS :has(), no JS */
  tbody.abl-group:not(:has(.grp-toggle:checked)) tr.abl-item{ display:none; }

  /* ---------- Pretty form (Create/Edit) ---------- */
  .form-card{ background:#fff; border:1px solid var(--ui-border,#d1d9e6); border-radius:12px; padding:14px; }
  .form-grid{ display:grid; grid-template-columns:minmax(260px, 380px); gap:12px; }
  .form .group label{ font-weight:700; margin-bottom:6px; display:block; }
  .form input[type="text"], .form textarea, .ui-select{
    width:100%; border:1px solid #d1d9e6; border-radius:10px; padding:9px 12px; font:inherit;
    transition:border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
  }
  .ui-select{
    appearance:none; -webkit-appearance:none; -moz-appearance:none; padding-right:38px; background:#fff
      url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23607080' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>")
      no-repeat right 10px center/14px; box-shadow:inset 0 1px 0 rgba(0,0,0,.02);
  }
  .form input[type="text"]:focus, .form textarea:focus, .ui-select:focus{ outline:0; border-color:#9ab8ff; box-shadow:0 0 0 3px rgba(130,160,255,.25); }
  .form small.muted{ color:#6b7280; }
</style>

<?php
  $e = static function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

  $perm      = $perm ?? ['can_manage' => false];
  $canManage = !empty($perm['can_manage']);

  $tab = in_array(($tab ?? 'index'), ['index','create','edit'], true) ? $tab : 'index';
  if (!$canManage && $tab !== 'index') { $tab = 'index'; }

  // Для edit
  $ability = $ability ?? null;
  $aid  = (int)($ability['id'] ?? 0);
  $name = (string)($ability['name'] ?? '');
  $label= (string)($ability['label'] ?? '');
  $desc = (string)($ability['description'] ?? '');
  $minR = (int)($ability['min_role'] ?? 0);

  // flash content (когда валидация упала на создании)
  $flash = isset($flash) ? $flash : [];
  $old   = isset($flash['content']) ? (array)$flash['content'] : [];

  // Название роли
  $roleLabel = function ($rid) use ($roles, $e) {
    foreach ($roles ?? [] as $r) {
      if ((int)$r['id'] === (int)$rid) {
        $lbl = trim((string)($r['label'] ?? '')); $nm  = trim((string)($r['name'] ?? ''));
        return $e($lbl !== '' ? $lbl : ($nm !== '' ? $nm : $rid));
      }
    }
    return $e($rid);
  };

  // Группировка по первому слову (fallback, если контроллер не дал $groups)
  if ((empty($groups) || !is_array($groups)) && !empty($abilities ?? [])) {
    $groups = [];
    foreach ($abilities as $ab) {
      $nm = (string)($ab['name'] ?? '');
      $dot = strpos($nm, '.');
      $prefix = ($dot !== false) ? substr($nm, 0, $dot) : $nm;
      if (!isset($groups[$prefix])) {
        $groups[$prefix] = ['title' => $prefix, 'count' => 0, 'items' => []];
      }
      $groups[$prefix]['items'][] = [
        'id' => (int)($ab['id'] ?? 0),
        'name' => $nm,
        'label' => (string)($ab['label'] ?? ''),
        'min_role' => (int)($ab['min_role'] ?? 0),
        'min_role_label' => $roleLabel((int)($ab['min_role'] ?? 0)),
      ];
      $groups[$prefix]['count']++;
    }
    ksort($groups, SORT_NATURAL);
    foreach ($groups as $k => $g) {
      usort($groups[$k]['items'], function($a,$b){ return strnatcasecmp($a['name'],$b['name']); });
    }
  }

  $colsCount = $canManage ? 4 : 3;
?>

<h1 class="page-title" style="margin-top:0;">Abilities</h1>

<div class="toolbar toolbar--compact" style="margin-bottom:0px;">
  <a class="button button--ghost" href="/admin" title="Назад в админ-панель">
    <span class="icon" aria-hidden="true">←</span><span>Назад</span>
  </a>
</div>

<!-- краткое описание раздела -->
<details class="info-details">
  <summary>Что здесь есть</summary>
  <div class="info-details__body">
    <ul style="margin:6px 0 0 18px;">
      <li><strong>Список</strong> — способности сгруппированы по первому токену до точки (<code>admin.*</code>, <code>auth.*</code>, …)</li>
      <li>Подсказки и ошибки показываются вверху страницы.</li>
    </ul>
  </div>
</details>

<div class="tabs">
  <input id="t-index"  class="tab-input" type="radio" name="tab" <?= $tab==='index'  ? 'checked' : '' ?>>
  <?php if ($canManage): ?>
    <input id="t-create" class="tab-input" type="radio" name="tab" <?= $tab==='create' ? 'checked' : '' ?>>
    <input id="t-edit"   class="tab-input" type="radio" name="tab" <?= $tab==='edit'   ? 'checked' : '' ?>>
  <?php endif; ?>

  <div class="tabbar">
    <label class="tab-label" for="t-index">Список</label>
    <?php if ($canManage): ?>
      <label class="tab-label" for="t-create">Создание</label>
      <label class="tab-label" for="t-edit">Правка</label>
    <?php endif; ?>
  </div>

  <div class="panels">
    <div class="tabpage">
      <!-- СПИСОК -->
      <section id="p-index" class="panel">
        <?php if (empty($groups ?? [])): ?>
          <div class="card"><div class="card__body muted">Способностей пока нет.</div></div>
        <?php else: ?>
          <div class="table-card">
            <div class="table-wrap">
              <table class="table table--striped table--hover table--compact table--sticky table--stacked-on-mobile">
                <thead>
                  <tr>
                    <th style="width:44%;">Name</th>
                    <th style="width:36%;">Label</th>
                    <th style="width:14%;">Min&nbsp;Role</th>
                    <?php if ($canManage): ?><th>Действия</th><?php endif; ?>
                  </tr>
                </thead>

                <?php $first = true; foreach ($groups as $gName => $g): ?>
                  <?php $slug = strtolower(preg_replace('~[^a-z0-9_-]+~i','-',$gName)); ?>
                  <tbody class="abl-group" data-group="<?= $e($gName) ?>">
                    <tr class="abl-head">
                      <td colspan="<?= (int)$colsCount ?>">
                        <input class="grp-toggle" type="checkbox" id="grp-<?= $e($slug) ?>" <?= $first ? 'checked' : '' ?>>
                        <label class="grp-label" for="grp-<?= $e($slug) ?>">
                          <span class="grp-caret" aria-hidden="true"></span>
                          <span><strong><?= $e($g['title'] ?? $gName) ?>.*</strong></span>
                          <span class="grp-count"><?= (int)($g['count'] ?? 0) ?></span>
                        </label>
                      </td>
                    </tr>

                    <?php foreach (($g['items'] ?? []) as $it): ?>
                      <tr class="abl-item">
                        <td data-th="Name"><?= $e($it['name'] ?? '') ?></td>
                        <td data-th="Label"><span class="muted"><?= $e($it['label'] ?? '') ?></span></td>
                        <td data-th="Min Role"><span class="badge"><?= $e($it['min_role_label'] ?? (string)($it['min_role'] ?? '')) ?></span></td>
                        <?php if ($canManage): ?>
                          <td data-th="Действия">
                            <div class="actions">
                              <a class="icon-btn" href="/admin/abilities/<?= (int)($it['id'] ?? 0) ?>/edit" title="Править">
                                <span class="icon" aria-hidden="true">✏️</span>
                              </a>
                              <form method="POST" action="/admin/abilities/<?= (int)($it['id'] ?? 0) ?>/delete" class="inline-form"
                                    onsubmit="return confirm('Удалить ability?');">
                                <?= csrf_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">
                                  <span class="icon" aria-hidden="true">🗑</span>
                                </button>
                              </form>
                            </div>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                <?php $first = false; endforeach; ?>

              </table>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($canManage): ?>
      <!-- СОЗДАНИЕ -->
      <section id="p-create" class="panel">
        <div class="form-card">
          <form class="form" method="POST" action="/admin/abilities">
            <?= csrf_field() ?>
            <div class="form-grid">
              <div class="group">
                <label for="a-name">Name*</label>
                <input id="a-name" name="name" type="text" maxlength="100" required placeholder="unique_name"
                       value="<?= $e(isset($old['name']) ? $old['name'] : '') ?>">
              </div>

              <div class="group">
                <label for="a-label">Label</label>
                <input id="a-label" name="label" type="text" maxlength="100" placeholder="Человекочитаемая метка"
                       value="<?= $e(isset($old['label']) ? $old['label'] : '') ?>">
              </div>

              <div class="group">
                <label for="a-desc">Description</label>
                <textarea id="a-desc" name="description" rows="4" placeholder="Короткое описание (опционально)"><?= $e(isset($old['description']) ? $old['description'] : '') ?></textarea>
              </div>

              <div class="group">
                <label for="a-minrole">Min Role*</label>
                <select id="a-minrole" name="min_role" class="ui-select" required>
                  <?php
                    $prefer = isset($old['min_role']) ? (int)$old['min_role'] : 1; // по умолчанию 1
                    foreach (($roles ?? []) as $r):
                      $rid = (int)$r['id']; $sel = ($rid === (int)$prefer) ? 'selected' : '';
                  ?>
                    <option value="<?= $rid ?>" <?= $sel ?>>
                      [<?= $rid ?>] <?= $e($r['label'] ?? ($r['name'] ?? (string)$rid)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="muted">Вы видите роли не выше вашей — потолок применяется на бэке.</small>
              </div>
            </div>

            <div class="toolbar" style="justify-content:flex-end; margin-top:12px;">
              <button class="button" type="submit"><span class="icon">✚</span><span>Создать</span></button>
            </div>
          </form>
        </div>
      </section>

      <!-- ПРАВКА -->
      <section id="p-edit" class="panel">
        <?php if (!$ability): ?>
          <div class="form-card"><div class="card__body muted">Выберите ability во «Списке» для редактирования.</div></div>
        <?php else: ?>
          <div class="toolbar toolbar--compact" style="margin-bottom:0px;gap:0px;">
            <a class="button button--ghost" href="/admin/abilities" title="К списку">
              <span class="icon" aria-hidden="true">←</span><span>К списку</span>
            </a>
            <form method="POST" action="/admin/abilities/<?= $aid ?>/delete" class="inline-form"
                  onsubmit="return confirm('Удалить ability?');">
              <?= csrf_field() ?>
              <button class="button button--danger" type="submit">
                <span class="icon" aria-hidden="true">🗑</span><span>Удалить</span>
              </button>
            </form>
          </div>

          <div class="form-card">
            <form class="form" method="POST" action="/admin/abilities/<?= $aid ?>">
              <?= csrf_field() ?>
              <div class="form-grid">
                <div class="group">
                  <label for="e-name">Name*</label>
                  <input id="e-name" name="name" type="text" maxlength="100" value="<?= $e($name) ?>" required>
                </div>
                <div class="group">
                  <label for="e-label">Label</label>
                  <input id="e-label" name="label" type="text" maxlength="100" value="<?= $e($label) ?>">
                </div>
                <div class="group">
                  <label for="e-desc">Description</label>
                  <textarea id="e-desc" name="description" rows="4"><?= $e($desc) ?></textarea>
                </div>
                <div class="group">
                  <label for="e-minrole">Min Role*</label>
                  <select id="e-minrole" name="min_role" class="ui-select" required>
                    <?php foreach (($roles ?? []) as $r): ?>
                      <?php $sel = ((int)$r['id'] === (int)$minR) ? 'selected' : ''; ?>
                      <option value="<?= (int)$r['id'] ?>" <?= $sel ?>>
                        [<?= (int)$r['id'] ?>] <?= $e($r['label'] ?? ($r['name'] ?? (string)$r['id'])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="toolbar" style="justify-content:space-between; margin-top:12px;">
                <a class="button" href="/admin/abilities">
                  <span class="icon" aria-hidden="true">↩</span><span>Отмена</span>
                </a>
                <button class="button" type="submit">
                  <span class="icon" aria-hidden="true">✔</span><span>Сохранить</span>
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>
@endsection
