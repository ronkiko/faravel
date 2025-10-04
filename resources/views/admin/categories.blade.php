<!-- v0.4.2 -->
<!-- resources/views/admin/categories.blade.php
Назначение: список категорий (id,title,slug,description).
FIX: Поля приведены к контракту контроллера: $categories с title/slug/description.
-->
@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title">Категории</h1>
  <p class="muted" style="margin:6px 0 16px;">Управляйте разделами верхнего уровня.</p>

  <div style="margin:0 0 12px;">
    <a class="btn" href="/admin/categories/create">Создать категорию</a>
  </div>

  @if ($categories)
    <div class="card" style="padding:0;">
      <table class="table" style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; padding:8px;">ID</th>
            <th style="text-align:left; padding:8px;">Название</th>
            <th style="text-align:left; padding:8px;">Слаг</th>
            <th style="text-align:left; padding:8px;">Описание</th>
            <th style="text-align:left; padding:8px;">Действия</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($categories as $c)
            <tr>
              <td style="padding:8px;">{{ $c['id'] }}</td>
              <td style="padding:8px;">{{ $c['title'] }}</td>
              <td style="padding:8px;">{{ $c['slug'] }}</td>
              <td style="padding:8px;">{{ $c['description'] }}</td>
              <td style="padding:8px;">
                <a href="/admin/categories/{{ $c['id'] }}/edit">Редактировать</a>
                |
                <a href="/admin/categories/{{ $c['id'] }}/forums">Форумы</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="card"><div class="muted">Категорий нет. Добавьте первую.</div></div>
  @endif
@endsection
