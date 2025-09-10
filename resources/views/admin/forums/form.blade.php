<!-- v0.1.0 — форма создания/правки форума; категории multiple; без inline-проверок ролей -->
@extends('layouts.main_admin')

@section('admin_content')
  <?php
    $isEdit = ($mode === 'edit');
    $f   = $forum ?? null;
    $fid = $isEdit ? (string)$f['id'] : '';
    $e   = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $values = [
      'title'           => $isEdit ? (string)$f['title']           : '',
      'slug'            => $isEdit ? (string)$f['slug']            : '',
      'description'     => $isEdit ? (string)($f['description'] ?? '') : '',
      'parent_forum_id' => $isEdit ? (string)($f['parent_forum_id'] ?? '') : '',
      'order_id'        => $isEdit ? (string)($f['order_id'] ?? '') : '',
      'is_visible'      => $isEdit ? (int)($f['is_visible'] ?? 0) : 1,
      'is_locked'       => $isEdit ? (int)($f['is_locked'] ?? 0)  : 0,
      'min_group'       => $isEdit ? (int)($f['min_group'] ?? 0)  : 0,
    ];

    $action = $isEdit ? "/admin/forums/{$fid}" : "/admin/forums";
  ?>

  <h1 class="page-title" style="margin-top:0;"><?= $isEdit ? 'Правка форума' : 'Новый форум' ?></h1>
  @include('layouts.flash')

  <!-- Хлебные крошки / тулбар -->
  <div class="toolbar toolbar--compact" style="margin-bottom:10px;">
    <a class="button button--ghost" href="/admin/forums" title="К списку">
      <span class="icon" aria-hidden="true">←</span><span>К списку форумов</span>
    </a>
    <?php if ($isEdit): ?>
      <form method="POST" action="/admin/forums/<?= $e($fid) ?>/delete" class="inline-form"
            onsubmit="return confirm('Удалить форум? Дочерние должны быть удалены/перемещены.')">
        <?= csrf_field() ?>
        <button class="button button--danger" type="submit"><span class="icon">🗑</span><span>Удалить</span></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="box-form box-form--wide">
    <form class="form" method="POST" action="<?= $e($action) ?>">
      <?= csrf_field() ?>

      <div class="group">
        <label for="f-title">Название</label>
        <input id="f-title" name="title" type="text" maxlength="200" value="<?= $e($values['title']) ?>" required>
      </div>

      <div class="group">
        <label for="f-slug">Slug (опционально)</label>
        <input id="f-slug" name="slug" type="text" maxlength="200" value="<?= $e($values['slug']) ?>">
        <small>Если оставить пустым — будет сгенерирован автоматически.</small>
      </div>

      <div class="group">
        <label for="f-desc">Описание</label>
        <textarea id="f-desc" name="description" rows="4"><?= $e($values['description']) ?></textarea>
      </div>

      <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <label class="group">
          <div>Родительский форум</div>
          <select name="parent_forum_id">
            <option value="">— нет —</option>
            <?php foreach (($forumsList ?? []) as $opt): ?>
              <?php $sel = ((string)$opt['id'] === (string)$values['parent_forum_id']) ? 'selected' : ''; ?>
              <option value="<?= $e($opt['id']) ?>" <?= $sel ?>><?= $e($opt['title']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($parentTitle) && $isEdit): ?>
            <small>Текущий родитель: <strong><?= $e($parentTitle) ?></strong></small>
          <?php endif; ?>
        </label>

        <label class="group">
          <div>Порядок (order_id)</div>
          <input type="number" name="order_id" value="<?= $e($values['order_id']) ?>">
        </label>

        <label class="group">
          <div>Min Group</div>
          <select name="min_group">
            <?php foreach (($groups ?? []) as $g): ?>
              <?php $sel = ((int)$g['id'] === (int)$values['min_group']) ? 'selected' : ''; ?>
              <option value="<?= (int)$g['id'] ?>" <?= $sel ?>>[<?= (int)$g['id'] ?>] <?= $e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
        <label class="group" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="is_visible" value="1" <?= $values['is_visible'] ? 'checked' : '' ?>>
          <span>Виден (is_visible)</span>
        </label>

        <label class="group" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="is_locked" value="1" <?= $values['is_locked'] ? 'checked' : '' ?>>
          <span>Закрыт (is_locked)</span>
        </label>
      </div>

      <div class="group">
        <label for="f-cats">Категории (множественный выбор)</label>
        <select id="f-cats" name="categories[]" multiple size="6">
          <?php
            $selected = array_map('strval', $selectedCategories ?? []);
            foreach (($categories ?? []) as $c):
              $cid = (string)$c['id'];
              $sel = in_array($cid, $selected, true) ? 'selected' : '';
          ?>
            <option value="<?= $e($cid) ?>" <?= $sel ?>><?= $e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($ctxCategory) && !$isEdit): ?>
          <small>Контекст: вы пришли из категории <strong><?= $e($ctxCategory['title']) ?></strong> — её уже выделили.</small>
        <?php endif; ?>
      </div>

      <div class="toolbar" style="justify-content:space-between;">
        <a class="button button--muted" href="/admin/forums"><span class="icon">↩</span><span>Отмена</span></a>
        <button class="button" type="submit" title="<?= $isEdit ? 'Сохранить' : 'Создать' ?>">
          <span class="icon"><?= $isEdit ? '✔' : '✚' ?></span>
          <span><?= $isEdit ? 'Сохранить' : 'Создать' ?></span>
        </button>
      </div>
    </form>
  </div>
@endsection
