<!-- v0.1.2 (admin perks form: добавлена шапка-версия; поведение без изменений) -->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_ver('/style/ui-box.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    /* сделать textarea такой же по стилю и ширине, как input */
    .box-form .group textarea{
      display:block;
      width:100%;
      box-sizing:border-box;
      padding:10px;
      font-size:1rem;
      border:1px solid #ccc;
      border-radius:4px;
      outline:none;
      background:#fff;
      resize:vertical;           /* разрешим тянуть по высоте */
      min-height:140px;          /* немного выше по умолчанию */
    }
    .box-form .group textarea:focus{ border-color:#3366cc; }

    /* опционально: более широкая форма для админок */
    .box-form--wide{ max-width:820px; }
  </style>
@endpush

@section('content')
  <?php
    $isEdit = ($mode === 'edit');
    $action = $isEdit ? "/admin/perks/{$perk['id']}" : "/admin/perks";
    $values = [
      'key'          => $isEdit ? (string)$perk['key']           : (string)($flash['content']['key'] ?? ''),
      'label'        => $isEdit ? (string)($perk['label'] ?? '') : (string)($flash['content']['label'] ?? ''),
      'description'  => $isEdit ? (string)($perk['description'] ?? '') : (string)($flash['content']['description'] ?? ''),
      'min_group_id' => $isEdit ? (int)$perk['min_group_id']     : (int)($flash['content']['min_group_id'] ?? 0),
    ];
  ?>

  <div class="toolbar toolbar--compact" style="margin-top:6px;">
    <a class="button button--ghost" href="/admin/perks" title="Назад к списку">
      <span class="icon">←</span><span>Назад</span>
    </a>
  </div>

  <!-- если хочешь широкую карточку — добавь класс box-form--wide -->
  <div class="box-form box-form--wide">
    <h1 class="title"><?= $isEdit ? 'Правка perk' : 'Новый perk' ?></h1>

    <?php if (!empty($flash['error'])): ?>
      <div class="error-message"><?= htmlspecialchars($flash['error'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="form" method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

      <div class="group">
        <label for="f-key">Key (уникальное)</label>
        <input id="f-key" type="text" name="key" value="<?= htmlspecialchars($values['key'], ENT_QUOTES, 'UTF-8') ?>" required>
        <small>Формат: <code>perk.profile.signature.use</code></small>
      </div>

      <div class="group">
        <label for="f-label">Label</label>
        <input id="f-label" type="text" name="label" value="<?= htmlspecialchars($values['label'], ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="group">
        <label for="f-min-group">Min Group ID</label>
        <input id="f-min-group" type="number" name="min_group_id" min="0" max="127" step="1" value="<?= (int)$values['min_group_id'] ?>" required>
        <small>Перк активен начиная с указанной группы (например, <code>2</code>).</small>
      </div>

      <div class="group">
        <label for="f-desc">Description</label>
        <textarea id="f-desc" name="description" rows="6"><?= htmlspecialchars($values['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="toolbar" style="justify-content:space-between;">
        <a class="button button--muted" href="/admin/perks" title="Отмена"><span class="icon">↩</span><span>Отмена</span></a>
        <button class="button" type="submit" title="<?= $isEdit ? 'Сохранить' : 'Создать' ?>">
          <span class="icon"><?= $isEdit ? '✔' : '✚' ?></span>
          <span><?= $isEdit ? 'Сохранить' : 'Создать' ?></span>
        </button>
      </div>
    </form>
  </div>
@endsection
