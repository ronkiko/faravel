<!-- resources/views/admin/categories.blade.php -->
<!-- v0.4.1 — Admin Categories (tabs: index/create/edit, CSS-only)
     CHANGE: в форме "Правка" поле min_group заменено на <select> с названиями групп. -->
@extends('layouts.main_admin')

@push('styles')
<style>
  .tabs { --gap: 10px; }
  .tabs .tab-input { position:absolute; left:-9999px; }
  .tabs .tab-labels { display:flex; gap:var(--gap); margin-bottom:12px; flex-wrap:wrap; }
  .tabs .tab-label {
    cursor:pointer; user-select:none; padding:8px 12px; border-radius:8px;
    background:var(--ui-bg-2, #f2f3f5); color:var(--ui-fg, #222); font-weight:600;
    border:1px solid var(--ui-border, #ddd);
  }
  .tabs .tab-label:hover { filter:brightness(0.98); }
  .tabs .panel { display:none; }
  #t-index:checked ~ .tab-labels label[for="t-index"],
  #t-create:checked ~ .tab-labels label[for="t-create"],
  #t-edit:checked  ~ .tab-labels label[for="t-edit"] {
    background:var(--ui-accent-bg, #e8f0ff);
    border-color:var(--ui-accent-border, #bcd3ff);
    color:var(--ui-accent-fg, #1a4fbf);
  }
  #t-index:checked ~ .panels #p-index { display:block; }
  #t-create:checked ~ .panels #p-create { display:block; }
  #t-edit:checked  ~ .panels #p-edit  { display:block; }
</style>
@endpush

@section('admin_content')
  <?php
    $e = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    // Какая вкладка активна
    $tab = in_array(($tab ?? 'index'), ['index','create','edit'], true) ? $tab : 'index';

    // Данные для вкладки "Правка"
    $cat   = $cat ?? [];
    $cid   = (string)($cat['id'] ?? '');
    $title = (string)($cat['title'] ?? '');
    $slug  = (string)($cat['slug'] ?? '');
    $desc  = (string)($cat['description'] ?? '');
    $order = (string)($cat['order_id'] ?? '');
    $vis   = (int)($cat['is_visible'] ?? 0);
    $mg    = (int)($cat['min_group'] ?? 0);

    // Список групп (ожидается из контроллера). Безопасный фоллбэк — пустой массив.
    $groups = $groups ?? [];
  ?>

  <h1 class="page-title" style="margin-top:0;">Категории</h1>
  @include('layouts.flash')

  <div class="toolbar toolbar--compact" style="margin-bottom:10px;">
    <a class="button button--ghost" href="/admin" title="Назад в админ-панель">
      <span class="icon" aria-hidden="true">←</span><span>Назад</span>
    </a>
  </div>

  <div class="tabs">
    <input id="t-index"  class="tab-input" type="radio" name="tab" {{ $tab==='index'  ? 'checked' : '' }}>
    <input id="t-create" class="tab-input" type="radio" name="tab" {{ $tab==='create' ? 'checked' : '' }}>
    <input id="t-edit"   class="tab-input" type="radio" name="tab" {{ $tab==='edit'   ? 'checked' : '' }}>

    <div class="tab-labels">
      <label class="tab-label" for="t-index">Список</label>
      <label class="tab-label" for="t-create">Создание</label>
      <label class="tab-label" for="t-edit">Правка</label>
    </div>

    <div class="panels">
      <!-- Список -->
      <section id="p-index" class="panel">
        <div class="card table-card">
          <div class="table-wrap">
            <table class="table table--striped table--hover table--compact table--sticky table--stacked-on-mobile">
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Slug</th>
                  <th>Order</th>
                  <th>Visible</th>
                  <th>Min&nbsp;Group</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach (($categories ?? []) as $c): ?>
                <?php
                  $id  = (string)$c['id'];
                  $t   = $e($c['title'] ?? '');
                  $sl  = $e($c['slug'] ?? '');
                  $ord = $e($c['order_id'] ?? '');
                  $vis = (int)($c['is_visible'] ?? 0) ? 'yes' : 'no';
                  $mgv = (int)($c['min_group'] ?? 0);
                ?>
                <tr>
                  <td data-th="Title"><?= $t ?></td>
                  <td data-th="Slug"><span class="muted"><?= $sl ?></span></td>
                  <td data-th="Order"><?= $ord ?></td>
                  <td data-th="Visible"><?= $vis ?></td>
                  <td data-th="Min Group"><span class="badge"><?= $mgv ?></span></td>
                  <td data-th="Действия">
                    <div class="actions">
                      <a class="icon-btn" href="/admin/categories/<?= $e($id) ?>/edit" title="Править">
                        <span class="icon" aria-hidden="true">✏️</span>
                      </a>
                      <form method="POST" action="/admin/categories/<?= $e($id) ?>/delete" class="inline-form"
                            onsubmit="return confirm('Удалить категорию?');">
                        <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
                        <input type="hidden" name="id" value="<?= $e($id) ?>">
                        <button class="icon-btn icon-btn--danger" type="submit" title="Удалить">
                          <span class="icon" aria-hidden="true">🗑</span>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($categories)): ?>
                <tr><td colspan="6" class="muted" style="padding:8px;">Категорий пока нет.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Создание -->
      <section id="p-create" class="panel">
        <div class="card" style="margin-bottom:14px;">
          <div class="card__body">
            <form class="form" method="POST" action="/admin/categories">
              <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

              <div class="grid" style="display:grid;grid-template-columns:2fr 3fr auto;gap:12px;align-items:end;">
                <label class="group" style="margin:0;">
                  <div>Название*</div>
                  <input type="text" name="name" maxlength="255" required placeholder="Напр. Общая категория">
                </label>

                <label class="group" style="margin:0;">
                  <div>Описание</div>
                  <input type="text" name="description" maxlength="1000" placeholder="Короткое описание (опционально)">
                </label>

                <button class="button" type="submit" style="height:42px;">
                  <span class="icon" aria-hidden="true">✚</span><span>Создать</span>
                </button>
              </div>
              <small class="muted">Slug и порядок выставятся автоматически. Видимость и min_group — по умолчанию БД.</small>
            </form>
          </div>
        </div>
      </section>

      <!-- Правка -->
      <section id="p-edit" class="panel">
        <?php if ($cid === ''): ?>
          <div class="card"><div class="card__body">
            <div class="muted">Выберите категорию во вкладке «Список» для редактирования.</div>
          </div></div>
        <?php else: ?>
          <div class="toolbar toolbar--compact" style="margin-bottom:10px;gap:8px;">
            <a class="button button--ghost" href="/admin/categories" title="К списку">
              <span class="icon" aria-hidden="true">←</span><span>К списку</span>
            </a>

            <form method="POST" action="/admin/categories/<?= $e($cid) ?>/delete" class="inline-form"
                  onsubmit="return confirm('Удалить категорию? Связанные записи могут запретить удаление.');">
              <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
              <input type="hidden" name="id" value="<?= $e($cid) ?>">
              <button class="button button--danger" type="submit">
                <span class="icon" aria-hidden="true">🗑</span><span>Удалить</span>
              </button>
            </form>
          </div>

          <div class="box-form box-form--wide">
            <form class="form" method="POST" action="/admin/categories/<?= $e($cid) ?>/edit">
              <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
              <input type="hidden" name="id" value="<?= $e($cid) ?>">

              <div class="group">
                <label for="f-title">Название*</label>
                <input id="f-title" name="title" type="text" maxlength="255" value="<?= $e($title) ?>" required>
              </div>

              <div class="group">
                <label for="f-slug">Slug</label>
                <input id="f-slug" name="slug" type="text" maxlength="100" value="<?= $e($slug) ?>">
                <small>Если оставить пустым — сгенерируется из названия.</small>
              </div>

              <div class="group">
                <label for="f-desc">Описание</label>
                <textarea id="f-desc" name="description" rows="4"><?= $e($desc) ?></textarea>
              </div>

              <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <label class="group">
                  <div>Порядок (order_id)*</div>
                  <input type="number" name="order_id" min="1" max="255" step="1" value="<?= $e($order) ?>" required>
                </label>

                <label class="group" style="display:flex;align-items:center;gap:8px;">
                  <input type="hidden" name="is_visible" value="0">
                  <input type="checkbox" id="f-visible" name="is_visible" value="1" <?= $vis ? 'checked' : '' ?>>
                  <span>Виден (is_visible)</span>
                </label>

                <!-- CHANGED: min_group как выпадающий список с метками -->
                <label class="group">
                  <div>Min Group*</div>
                  <?php if (!empty($groups)): ?>
                    <select name="min_group" required>
                      <?php foreach ($groups as $g):
                        $gid = (int)($g['id'] ?? 0);
                        $sel = ($gid === $mg) ? 'selected' : '';
                        $gname = $e($g['name'] ?? ('group '.$gid));
                      ?>
                        <option value="<?= $gid ?>" <?= $sel ?>>[<?= $gid ?>] <?= $gname ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <!-- Фоллбэк: если список групп пуст, не ломаем форму -->
                    <input type="number" name="min_group" min="0" max="255" step="1" value="<?= $e($mg) ?>" required>
                    <small class="muted">Список групп не передан — отображено числовое поле.</small>
                  <?php endif; ?>
                </label>
              </div>

              <div class="toolbar" style="justify-content:space-between;">
                <a class="button button--muted" href="/admin/categories">
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
    </div>
  </div>
@endsection
