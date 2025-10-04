<!-- v0.4.2 -->
<!-- resources/views/admin/perks.blade.php
Назначение: единая админ-страница управления «перками» (Perks) в стиле abilities.blade.
Показывает список перков и форму создания/редактирования на одной странице.
FIX: layout теперь объект LayoutVM: заменён $layout['csrf'] → {{ $layout->csrf }}.
-->

@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title" style="margin-top:0;">Perks</h1>
  <p class="muted" style="margin:6px 0 16px;">Косметические опции, доступные с минимального уровня группы.</p>

  @if ($has_error)
    <div class="alert alert--error">{{ $flash_error }}</div>
  @endif
  @if ($has_success)
    <div class="alert alert--success">{{ $flash_success }}</div>
  @endif

  <div class="card" style="margin-bottom:16px;">
    <div class="card__header"><h2 style="margin:0;">Список перков</h2></div>
    <div class="card__body">
      @if ($perks)
        <div class="table-wrap" style="overflow:auto;">
          <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th style="text-align:left; padding:8px;">ID</th>
                <th style="text-align:left; padding:8px;">Key</th>
                <th style="text-align:left; padding:8px;">Info</th>
                <th style="text-align:left; padding:8px;">Min&nbsp;Group</th>
                <th style="text-align:left; padding:8px;">Действия</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($perks as $p)
                <tr>
                  <td style="padding:8px;">{{ $p['id'] }}</td>
                  <td style="padding:8px;">{{ $p['key'] }}</td>
                  <td style="padding:8px;">
                    @if ($p['label'])
                      <div><strong>{{ $p['label'] }}</strong></div>
                    @endif
                    @if ($p['description'])
                      <div class="muted">{{ $p['description'] }}</div>
                    @endif
                    @if (!$p['label'] && !$p['description'])
                      <span class="muted">—</span>
                    @endif
                  </td>
                  <td style="padding:8px;">
                    <span class="badge">{{ $p['min_group_id'] }}</span>
                  </td>
                  <td style="padding:8px;">
                    <div class="actions">
                      @if ($perm['can_manage'])
                        <a class="btn btn--sm" href="{{ $p['edit_url'] }}">Править</a>
                        <form method="POST" action="{{ $p['delete_action'] }}" class="inline-form" style="display:inline-block; margin-left:6px;">
                          <input type="hidden" name="_token" value="{{ $layout->csrf }}">
                          <input type="hidden" name="_method" value="DELETE">
                          <button class="btn btn--sm btn--danger" type="submit">Удалить</button>
                        </form>
                      @else
                        <span class="muted">—</span>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="muted">Перков пока нет. Создайте первый ниже.</div>
      @endif
    </div>
  </div>

  <div class="card" style="margin-bottom:16px; max-width:720px;">
    <div class="card__header">
      @if ($form_mode === 'edit')
        <h2 style="margin:0;">Правка перка #{{ $form['id'] }}</h2>
      @else
        <h2 style="margin:0;">Создать перк</h2>
      @endif
    </div>
    <div class="card__body">
      <form class="form" action="{{ $form_action }}" method="POST">
        <input type="hidden" name="_token" value="{{ $layout->csrf }}">
        @if ($form_http_method)
          <input type="hidden" name="_method" value="{{ $form_http_method }}">
        @endif

        <div class="group" style="margin-bottom:10px;">
          <label for="key">Key</label>
          <input id="key" name="key" type="text" value="{{ $form['key'] }}">
        </div>

        <div class="group" style="margin-bottom:10px;">
          <label for="label">Label</label>
          <input id="label" name="label" type="text" value="{{ $form['label'] }}">
        </div>

        <div class="group" style="margin-bottom:10px;">
          <label for="description">Описание</label>
          <textarea id="description" name="description" rows="3">{{ $form['description'] }}</textarea>
        </div>

        <div class="group" style="margin-bottom:12px;">
          <label for="min_group_id">Минимальная группа</label>
          <select id="min_group_id" name="min_group_id">
            @foreach ($groups as $g)
              <option value="{{ $g['id'] }}"
                @if ($form['min_group_id'] == $g['id']) selected @endif
              >{{ $g['name'] }}</option>
            @endforeach
          </select>
        </div>

        <button class="btn btn--primary" type="submit">
          @if ($form_mode === 'edit') Сохранить @else Создать @endif
        </button>
        <a class="btn" href="/admin">Отмена</a>
      </form>
    </div>
  </div>
@endsection
