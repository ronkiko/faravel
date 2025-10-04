<!-- v0.4.18 -->
<!-- resources/views/admin/forums.blade.php
Назначение: список форумов и форма создания нового форума. Строгий Blade:
-->
@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title" style="margin-top:0;">Форумы</h1>
  <p class="muted" style="margin:6px 0 16px;">Список существующих форумов и создание нового.</p>

  @if ($has_error)
    <div class="alert alert--error">{{ $flash_error }}</div>
  @endif
  @if ($has_success)
    <div class="alert alert--success">{{ $flash_success }}</div>
  @endif

  <div class="card" style="margin-bottom:16px;">
    <div class="card__header"><h2 style="margin:0;">Список форумов</h2></div>
    <div class="card__body">
      @if ($forums)
        <div class="table-wrap" style="overflow:auto;">
          <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
              <tr>
                <th style="text-align:left; padding:8px;">ID</th>
                <th style="text-align:left; padding:8px;">Название</th>
                <th style="text-align:left; padding:8px;">Слаг</th>
                <th style="text-align:left; padding:8px;">Категория</th>
                <th style="text-align:left; padding:8px;">Тем</th>
                <th style="text-align:left; padding:8px;">Сообщений</th>
                <th style="text-align:left; padding:8px;">Обновлён</th>
                <th style="text-align:left; padding:8px;">Действия</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($forums as $f)
                <tr>
                  <td style="padding:8px;">{{ $f['id'] }}</td>
                  <td style="padding:8px;">{{ $f['title'] }}</td>
                  <td style="padding:8px;">{{ $f['slug'] }}</td>
                  <td style="padding:8px;">{{ $f['category_name'] }}</td>
                  <td style="padding:8px;">{{ $f['topics_count'] }}</td>
                  <td style="padding:8px;">{{ $f['posts_count'] }}</td>
                  <td style="padding:8px;">{{ $f['updated_at'] }}</td>
                  <td style="padding:8px;">
                    <a href="{{ $f['edit_url'] }}">Редактировать</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="muted">Форумов нет. Создайте первый ниже.</div>
      @endif
    </div>
  </div>

  <div class="card" style="margin-bottom:16px; max-width:720px;">
    <div class="card__header"><h2 style="margin:0;">Создать форум</h2></div>
    <div class="card__body">
      <form class="form" action="{{ $create_action }}" method="POST">
        <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

        <div class="group" style="margin-bottom:10px;">
          <label for="title">Название</label>
          <input id="title" name="title" type="text" value="{{ $form['title'] }}">
        </div>

        <div class="group" style="margin-bottom:10px;">
          <label for="slug">Слаг</label>
          <input id="slug" name="slug" type="text" value="{{ $form['slug'] }}">
          <div class="muted" style="font-size:.9rem;">Только латиница и дефисы</div>
        </div>

        <div class="group" style="margin-bottom:10px;">
          <label for="category_id">Категория</label>
          <select id="category_id" name="category_id">
            @foreach ($categories as $c)
              <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
            @endforeach
          </select>
        </div>

        <div class="group" style="margin-bottom:12px;">
          <label for="description">Описание</label>
          <textarea id="description" name="description" rows="3">{{ $form['description'] }}</textarea>
        </div>

        <button class="btn btn--primary" type="submit">Создать</button>
        <a class="btn" href="/admin">Отмена</a>
      </form>
    </div>
  </div>
@endsection
