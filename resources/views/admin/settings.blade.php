<!-- v0.4.2 -->
<!-- resources/views/admin/settings.blade.php
Назначение: форма настроек троттлинга. Поля из AdminController@settings.
FIX: Только {{ $layout['csrf'] }} и простые эхо переменных. Без функций и PHP.
-->
@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title">Настройки троттлинга</h1>
  <p class="muted" style="margin:6px 0 16px;">Ограничения запросов и исключения путей.</p>

  <form class="form form-card" action="/admin/settings/save" method="POST" style="max-width:640px;">
    <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

    <div class="group" style="margin-bottom:10px;">
      <label for="window_sec">Окно, сек</label>
      <input id="window_sec" name="window_sec" type="number" min="1" max="3600"
             value="{{ $window_sec }}">
    </div>

    <div class="group" style="margin-bottom:10px;">
      <label for="get_max">GET, максимум за окно</label>
      <input id="get_max" name="get_max" type="number" min="1" max="10000"
             value="{{ $get_max }}">
    </div>

    <div class="group" style="margin-bottom:10px;">
      <label for="post_max">POST, максимум за окно</label>
      <input id="post_max" name="post_max" type="number" min="1" max="10000"
             value="{{ $post_max }}">
    </div>

    <div class="group" style="margin-bottom:10px;">
      <label for="session_max">Сессии, максимум</label>
      <input id="session_max" name="session_max" type="number" min="1" max="50000"
             value="{{ $session_max }}">
    </div>

    <div class="group" style="margin-bottom:14px;">
      <label for="exempt_paths">Исключённые пути (по одному в строке)</label>
      <textarea id="exempt_paths" name="exempt_paths" rows="5">{{ $exempt_paths }}</textarea>
    </div>

    <button class="btn" type="submit">Сохранить</button>
  </form>
@endsection
