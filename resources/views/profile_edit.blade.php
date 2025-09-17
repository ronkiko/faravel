<!-- v0.3.25 (no-JS modals, #lang highlight, help icons no-underline & smaller) -->
@extends('layouts.theme')

@section('content')
<?php
$u   = $target ?? [];
$uid = (string)($u['id'] ?? '');
$isSelf   = (bool)($isSelf ?? false);
$canAdmin = (bool)($canAdmin ?? false);

$languages = is_array($languages ?? null) ? $languages : [];
$roles     = is_array($roles ?? null) ? $roles : [];
$groups    = is_array($groups ?? null) ? $groups : [];

$registeredDays  = $registeredDays ?? null;
$registeredDate  = $registeredDate ?? null;
$lastUpdatedDays = $lastUpdatedDays ?? null;
$lastPostHuman   = $lastPostHuman ?? '—';

function opt($arr, $k, $def=''){ return htmlspecialchars((string)($arr[$k] ?? $def), ENT_QUOTES, 'UTF-8'); }

// settings → массив
$settingsArr = [];
if (isset($u['settings']) && is_string($u['settings']) && $u['settings'] !== '') {
  $settingsArr = json_decode($u['settings'], true) ?: [];
}
$avatarShape = in_array($settingsArr['avatar_shape'] ?? 'square', ['square','circle','star'], true)
  ? $settingsArr['avatar_shape'] : 'square';
?>
<style>
.edit-page{ max-width: 860px; margin: 16px auto 0; }

/* «Табличная» сетка */
.kv{
  display:grid;
  grid-template-columns: 220px 1fr;
  column-gap:12px; row-gap:0;
  align-items:start;
  background:#fff;
  border:1px solid #e5e7eb; border-radius:4px;
}
.kv .cell{ padding:10px 12px; border-top:1px solid #e5e7eb; }
.kv .cell.k{ color:#374151; font-weight:600; }
.kv .cell.noborder{ border-top:none; }

.muted{ color:#6b7280; }
.note{ color:#6b7280; font-size:.9em; margin-top:4px; }
input[type="text"], input[type="number"], select, textarea {
  border:1px solid #d1d5db; padding:6px 8px; border-radius:3px; width:100%; background:#fff; color:#111827;
}
input[disabled], select[disabled], textarea[disabled]{ background:#f9fafb; color:#6b7280; }

.btn{ display:inline-block; padding:6px 10px; border:1px solid #d1d5db; background:#fff; color:#111827; border-radius:3px; cursor:pointer; text-decoration:none; }
.btn:hover{ background:#f3f4f6; }
.btn-compact{ padding:4px 8px; font-size:.9em; margin-left:8px; }

/* Иконки-подсказки: без подчёркивания и чуть меньше */
.help-btn{
  background:transparent; border:0; cursor:pointer; line-height:1;
  color:#6b7280; padding:0 2px; text-decoration:none; /* убрать underline */
  font-size:0.9em;                                      /* чуть меньше */
}
.help-btn:hover{ color:#111827; text-decoration:none; }
.help-inline{
  font-size:0.78em;             /* компактнее */
  margin-left:8px;
  position:relative; top:-1px;
  vertical-align:baseline;
}

/* Подсветка строки языка по якорю (#lang) и локальная кнопка только при :target */
#lang { scroll-margin-top: 72px; }
.lang-local-save{ display:none; }
#lang:target,
#lang:target + .cell {
  background: #fffceb;
  box-shadow: inset 0 0 0 2px #fcd34d;
  animation: langPulse 1.2s ease-in-out 0s 4;
}
#lang:target + .cell .lang-local-save{ display:inline-block; }

@keyframes langPulse {
  0%   { background:#fffceb; }
  50%  { background:#fde68a; }
  100% { background:#fffceb; }
}

/* Модалки на :target (без JS) */
.modal-overlay{
  position:fixed; inset:0; padding:16px;
  display:none; align-items:center; justify-content:center;
  background:rgba(17,24,39,.45); backdrop-filter: blur(2px);
  z-index:10050; overflow:auto;
}
.modal-overlay:target{ display:flex; }
.modal{
  background:#fff; width:100%; max-width:720px; max-height:92vh; overflow:auto;
  border-radius:6px; box-shadow:0 15px 40px rgba(0,0,0,.25);
  padding:16px 16px 12px;
}
.modal h3{ margin:0 0 8px; font-size:18px; }
.modal .modal-body{ color:#374151; }
.modal .modal-actions{ text-align:right; margin-top:12px; }
.modal .btn-close{ border-color:#d1d5db; }

@media (max-width:780px){
  .kv{ grid-template-columns: 1fr; }
}
@media (max-width:480px){
  .modal-overlay{ padding:8px; align-items:stretch; }
  .modal{ max-width:none; border-radius:4px; padding:12px; }
}
@media (max-width:240px){
  .modal h3{ font-size:16px; }
  .modal{ padding:10px; }
}
</style>

<div class="edit-page">
  <h1 style="margin:0 0 10px;">Редактирование профиля</h1>

  <?php if ($forbidden ?? false): ?>
    <p style="color:#b91c1c;">Недостаточно прав для редактирования этого профиля.</p>
    <p><a href="/profile" class="btn">Вернуться в профиль</a></p>
  <?php else: ?>

    <form method="POST" action="/profile/edit/save" style="display:grid; gap:12px;">
      <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
      <input type="hidden" name="user_id" value="<?= opt($u,'id') ?>">

      <div class="kv">
        <!-- ID -->
        <div class="cell k noborder">ID пользователя</div>
        <div class="cell noborder"><input type="text" value="<?= opt($u,'id') ?>" disabled></div>

        <!-- Username -->
        <div class="cell k">Имя пользователя</div>
        <div class="cell">
          <input type="text" name="username" value="<?= opt($u,'username') ?>" <?= ($isSelf || $canAdmin) ? '' : 'disabled' ?>>
        </div>

        <!-- Password -->
        <div class="cell k">Пароль</div>
        <div class="cell" style="display:flex; gap:8px; align-items:center;">
          <input type="text" value="********" disabled style="max-width:200px;">
          <a class="btn" href="#" aria-disabled="true" onclick="return false;">Сменить пароль (скоро)</a>
        </div>

        <!-- Avatar -->
        <div class="cell k">Аватар</div>
        <div class="cell">
          <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
              <?php
              if (function_exists('avatar_tag')) {
                echo avatar_tag($u['id'] ?? null, ['size'=>72, 'shape'=>$avatarShape, 'alt'=>($u['username'] ?? 'avatar')]);
              } else {
                $src = '/avatars/'.opt($u,'id').'.png';
                echo '<img src="'.htmlspecialchars($src,ENT_QUOTES,'UTF-8').'" alt="avatar" style="width:72px;height:72px;object-fit:cover;border:1px solid #d1d5db;border-radius:2px;">';
              }
              ?>
            </div>
            <div style="min-width:220px;">
              <label class="muted" for="avatar_shape">Форма</label>
              <select id="avatar_shape" name="avatar_shape" <?= ($isSelf || $canAdmin) ? '' : 'disabled' ?>>
                <option value="square" <?= $avatarShape==='square'?'selected':'' ?>>Квадрат</option>
                <option value="circle" <?= $avatarShape==='circle'?'selected':'' ?>>Круг</option>
                <option value="star"   <?= $avatarShape==='star'  ?'selected':'' ?>>Звезда</option>
              </select>
              <div class="note">Выбор влияет на отображение аватара в интерфейсе.</div>
            </div>
          </div>
        </div>

        <!-- Registered -->
        <div class="cell k">Зарегистрирован</div>
        <div class="cell">
          <?php if ($registeredDate): ?>
            <span><?= htmlspecialchars($registeredDate, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="muted">· на форуме <?= (int)$registeredDays ?> дн.</span>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </div>

        <!-- Reputation -->
        <div class="cell k">Репутация</div>
        <div class="cell">
          <input type="number" name="reputation" value="<?= (int)($u['reputation'] ?? 0) ?>" min="0" <?= $canAdmin ? '' : 'disabled' ?>>
          <div class="note">Чем выше репутация, тем выше статус сообщества.</div>
        </div>

        <!-- Group -->
        <div class="cell k">
          <span>Группа</span>
          <a class="help-btn help-inline" href="#modal-group" aria-label="Что такое группы?" role="button">❓</a>
        </div>
        <div class="cell">
          <select name="group_id" <?= $canAdmin ? '' : 'disabled' ?>>
            <?php $gid = (int)($u['group_id'] ?? 1); ?>
            <?php foreach ($groups as $g):
              $val   = (int)$g['id'];
              $name  = (string)$g['name'];
              $rep   = $g['reputation'] ?? null;
              $hint  = is_null($rep) ? '' : ' (≥ ' . (int)$rep . ' реп.)';
            ?>
              <option value="<?= $val ?>" <?= $val === $gid ? 'selected' : '' ?>>
                <?= htmlspecialchars($name . $hint, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="note">Группа меняется автоматически при достижении порога репутации.</div>
        </div>

        <!-- Role -->
        <div class="cell k">
          <span>Роль</span>
          <a class="help-btn help-inline" href="#modal-role" aria-label="Что такое роли?" role="button">❓</a>
        </div>
        <div class="cell">
          <select name="role_id" <?= $canAdmin ? '' : 'disabled' ?>>
            <?php $rid = (int)($u['role_id'] ?? 1); ?>
            <?php foreach ($roles as $r):
              $val = (int)$r['id'];
              $lbl = trim((string)($r['label'] ?? $r['name'] ?? ''));
            ?>
              <option value="<?= $val ?>" <?= $val === $rid ? 'selected' : '' ?>>
                <?= htmlspecialchars($lbl !== '' ? $lbl : ('role#'.$val), ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="note">Роль определяет доступ и полномочия на форуме.</div>
        </div>

        <!-- Language -->
        <div class="cell k" id="lang">Язык интерфейса</div>
        <div class="cell">
          <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            <select name="language_id" <?= ($isSelf || $canAdmin) ? '' : 'disabled' ?>>
              <?php $lid = (int)($u['language_id'] ?? 1); ?>
              <?php foreach ($languages as $lg):
                $val=(int)$lg['id']; $txt=trim(($lg['name'] ?? '').' ('.($lg['code'] ?? '').')');
              ?>
                <option value="<?= $val ?>" <?= $val === $lid ? 'selected' : '' ?>><?= htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-compact lang-local-save">Сохранить</button>
          </div>
          <div class="note">Выбранный язык сохранится в профиле и вступит в силу сразу.</div>
        </div>

        <!-- Title -->
        <div class="cell k">Title</div>
        <div class="cell"><input type="text" value="<?= opt($u,'title') ?>" disabled></div>

        <!-- Style -->
        <div class="cell k">Style</div>
        <div class="cell"><input type="number" value="<?= (int)($u['style'] ?? 0) ?>" disabled></div>

        <!-- Signature -->
        <div class="cell k">Signature</div>
        <div class="cell"><textarea rows="3" disabled><?= opt($u,'signature') ?></textarea></div>

        <!-- Last post -->
        <div class="cell k">Last post</div>
        <div class="cell"><input type="text" value="<?= htmlspecialchars($lastPostHuman, ENT_QUOTES, 'UTF-8') ?>" disabled></div>
      </div>

      <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
        <button type="submit" class="btn">Сохранить</button>
        <a href="/profile" class="btn">Отмена</a>
      </div>

      <?php if ($lastUpdatedDays !== null): ?>
        <div class="muted" style="font-style:italic; margin-top:4px;">
          Последнее обновление: <?= (int)$lastUpdatedDays ?> дн. назад
        </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>

<!-- МОДАЛКИ (на :target) -->
<div id="modal-group" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-group-title">
  <div class="modal">
    <h3 id="modal-group-title">О группах</h3>
    <div class="modal-body">
      <p>Группа — это «уровень участия» в сообществе. Она определяется порогом репутации и
         <b>может назначаться автоматически</b> при достижении нужного значения.</p>
      <?php if ($groups): ?>
        <table style="width:100%; border-collapse:collapse; margin-top:8px;">
          <thead>
            <tr><th style="text-align:left; padding:6px 0; border-bottom:1px solid #e5e7eb;">Группа</th>
                <th style="text-align:left; padding:6px 0; border-bottom:1px solid #e5e7eb;">Порог репутации</th></tr>
          </thead>
          <tbody>
          <?php foreach ($groups as $g):
            $name = htmlspecialchars((string)$g['name'], ENT_QUOTES, 'UTF-8');
            $rep  = $g['reputation'] ?? null;
            $thr  = is_null($rep) ? '—' : ('≥ '.(int)$rep);
          ?>
            <tr>
              <td style="padding:6px 0; border-bottom:1px solid #f3f4f6;"><?= $name ?></td>
              <td style="padding:6px 0; border-bottom:1px solid #f3f4f6;"><?= $thr ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <div class="modal-actions">
        <a href="#" class="btn btn-close">Закрыть</a>
      </div>
    </div>
  </div>
</div>

<div id="modal-role" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-role-title">
  <div class="modal">
    <h3 id="modal-role-title">О ролях</h3>
    <div class="modal-body">
      <p>Роль — это набор прав (доступов) на форуме. Роли <b>не зависят</b> от репутации и назначаются вручную администраторами.</p>
      <?php if ($roles): ?>
        <ul style="margin:6px 0 0 18px;">
          <?php foreach ($roles as $r):
            $lbl = trim((string)($r['label'] ?? $r['name'] ?? ''));
            $name = htmlspecialchars($lbl !== '' ? $lbl : ('role#'.(int)$r['id']), ENT_QUOTES, 'UTF-8');
          ?>
            <li><?= $name ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="modal-actions">
        <a href="#" class="btn btn-close">Закрыть</a>
      </div>
    </div>
  </div>
</div>
@endsection
