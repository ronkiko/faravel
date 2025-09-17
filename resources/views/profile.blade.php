<!-- v0.3.18 -->
@extends('layouts.theme')

@section('content')
<?php
$u = $user ?? [];
$p = $profile ?? [];

$uid      = (string)($u['id'] ?? '');
$username = (string)($u['username'] ?? 'guest');

// ── settings: из сессии/контроллера может прийти строка JSON или массив
$settingsRaw = $u['settings'] ?? ($p['settings'] ?? null);
$settingsArr = [];
if (is_string($settingsRaw)) {
    $tmp = json_decode($settingsRaw, true);
    if (is_array($tmp)) $settingsArr = $tmp;
} elseif (is_array($settingsRaw)) {
    $settingsArr = $settingsRaw;
}

// avatar shape: поддерживаем и settings.avatar_shape, и settings.avatar.shape
$avatarShape = 'square';
if (isset($settingsArr['avatar_shape']) && in_array($settingsArr['avatar_shape'], ['square','circle','star'], true)) {
    $avatarShape = $settingsArr['avatar_shape'];
} elseif (isset($settingsArr['avatar']['shape']) && in_array($settingsArr['avatar']['shape'], ['square','circle','star'], true)) {
    $avatarShape = $settingsArr['avatar']['shape'];
}

// Приоритет: явно переданный аватар -> PNG по id -> дефолт (используется только в фолбэке без avatar_tag)
$avatar = trim((string)($p['avatar'] ?? ''));
if ($avatar === '' && $uid !== '') {
    $avatar = '/avatars/' . $uid . '.png';
}
if ($avatar === '') {
    $avatar = '/avatars/avatar-default.png';
}

$isActive = (bool)($p['isActive'] ?? true);
$roleLbl  = (string)($p['roleLabel'] ?? 'User');
$group    = (string)($p['groupName'] ?? '—');

$joined   = $p['joinedDate'] ?? null;
$daysOn   = (int)($p['registeredDays'] ?? 0);

$topics   = (int)($p['stats']['topics'] ?? 0);
$posts    = (int)($p['stats']['posts']  ?? 0);
$repute   = (int)($p['stats']['reputation'] ?? 0);

$sigRaw   = (string)($p['signature'] ?? '');
?>
<style>
.profile { max-width: 980px; margin: 12px auto 0; }
.header  { display:flex; align-items:center; gap:12px; }
.header .meta { display:flex; flex-direction:column; gap:4px; min-width:0; }
.avatar  { width: 72px; height: 72px; object-fit: cover; }
.username{ font-size: 1.6rem; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.badge { display:inline-block; padding:2px 6px; border:1px solid #d1d5db; border-radius:999px; font-size:.82rem; line-height:1.2; }
.badge.ok { color:#065f46; border-color:#a7f3d0; background:#ecfdf5; }
.badge.off{ color:#6b7280; background:#f9fafb; }

.muted{ color:#6b7280; }
.stat-grid{ display:grid; grid-template-columns: repeat(3, minmax(120px,1fr)); gap:8px; margin-top:10px; }
.card{ border:1px solid #e5e7eb; border-radius:4px; padding:8px 10px; background:#fff; }

.kv  { border:1px solid #e5e7eb; border-radius:4px; margin-top:10px; overflow:hidden; }
.kv table{ width:100%; border-collapse: collapse; }
.kv th, .kv td{ padding:8px 10px; border-top:1px solid #e5e7eb; text-align:left; }
.kv th{ width:220px; color:#374151; font-weight:600; background:#fafafa; }
.kv tr:first-child th, .kv tr:first-child td{ border-top:none; }

.sig { border-top:1px solid #e5e7eb; margin-top:12px; padding-top:10px; }
.sig .box{ border:1px dashed #d1d5db; border-radius:4px; padding:8px 10px; color:#374151; background:#fff; }

.actions{ margin-left:auto; display:flex; gap:8px; }
.actions form{ margin:0; }
.btn{ display:inline-block; padding:6px 10px; border:1px solid #d1d5db; background:#fff; color:#111827; border-radius:3px; cursor:pointer; }
.btn:hover{ background:#f3f4f6; }

/* формы аватарки — на случай если общий avatar.css не подключён */
.avatar--square{ border-radius:2px; }
.avatar--circle{ border-radius:999px; }
.avatar--star{
  clip-path: polygon(
    50% 0%, 61% 35%, 98% 35%,
    68% 57%, 79% 91%, 50% 70%,
    21% 91%, 32% 57%, 2% 35%,
    39% 35%
  );
  border-radius:0;
}

@media (max-width: 560px){
  .avatar{ width:56px; height:56px; }
  .username{ font-size:1.3rem; }
  .stat-grid{ grid-template-columns: 1fr 1fr 1fr; }
  .actions{ margin-left:0; }
  .header{ flex-wrap:nowrap; }
}
</style>

<?php if (!can('profile.view')): ?>
  <div class="profile">
    <p>Для просмотра профиля требуется авторизация.</p>
    <p><a href="/login" class="btn">Войти</a></p>
  </div>
<?php else: ?>
  <div class="profile">
    <!-- Шапка -->
    <div class="header">
      <?php if (function_exists('avatar_tag')): ?>
        <?= avatar_tag($u['id'] ?? null, ['size'=>72, 'shape'=>$avatarShape, 'alt'=>$username]) ?>
      <?php else: ?>
        <img class="avatar avatar--<?= htmlspecialchars($avatarShape, ENT_QUOTES, 'UTF-8') ?>"
             src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>"
             alt="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
      <?php endif; ?>

      <div class="meta">
        <div class="username">
          <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
          <?php if ($isActive): ?>
            <span class="badge ok" title="Аккаунт активен">Active</span>
          <?php else: ?>
            <span class="badge off" title="Аккаунт деактивирован">Inactive</span>
          <?php endif; ?>
        </div>

        <div class="muted">
          Роль: <b><?= htmlspecialchars($roleLbl, ENT_QUOTES, 'UTF-8') ?></b>
          &nbsp;•&nbsp;
          Группа: <b><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></b>
        </div>

        <div class="muted">
          <?php if ($joined): ?>
            На форуме: <b><?= (int)$daysOn ?></b> дн. (с <?= htmlspecialchars($joined, ENT_QUOTES, 'UTF-8') ?>)
          <?php else: ?>
            На форуме: —
          <?php endif; ?>
        </div>
      </div>

      <!-- Действия -->
      <div class="actions">
        <form method="POST" action="/logout">
          <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
          <button type="submit" class="btn">Выйти</button>
        </form>

        <form method="POST" action="/profile/edit">
          <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
          <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)($u['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="btn">Редактирование</button>
        </form>
      </div>
    </div>

    <!-- Сводная статистика -->
    <div class="stat-grid">
      <div class="card">
        <div class="muted">Тем</div>
        <div style="font-weight:700; font-size:1.2rem;"><?= $topics ?></div>
      </div>
      <div class="card">
        <div class="muted">Сообщений</div>
        <div style="font-weight:700; font-size:1.2rem;"><?= $posts ?></div>
      </div>
      <div class="card">
        <div class="muted">Репутация</div>
        <div style="font-weight:700; font-size:1.2rem;"><?= $repute ?></div>
      </div>
    </div>

    <!-- Детали -->
    <div class="kv">
      <table>
        <tr>
          <th>Имя пользователя</th>
          <td><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
          <th>Роль</th>
          <td><?= htmlspecialchars($roleLbl, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
          <th>Группа</th>
          <td><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
          <th>Стаж на форуме</th>
          <td>
            <?php if ($joined): ?>
              <?= (int)$daysOn ?> дн. (с <?= htmlspecialchars($joined, ENT_QUOTES, 'UTF-8') ?>)
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>Тем</th>
          <td><?= $topics ?></td>
        </tr>
        <tr>
          <th>Сообщений</th>
          <td><?= $posts ?></td>
        </tr>
        <tr>
          <th>Репутация</th>
          <td><?= $repute ?></td>
        </tr>
      </table>
    </div>

    <!-- Сигнатура (заглушка) -->
    <div class="sig">
      <div class="muted" style="margin-bottom:6px;">Подпись</div>
      <div class="box">
        <?php if (trim($sigRaw) === ''): ?>
          <span class="muted">Подпись пока не задана.</span>
        <?php else: ?>
          <?= nl2br(htmlspecialchars($sigRaw, ENT_QUOTES, 'UTF-8')) ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
<?php endif; ?>
@endsection
