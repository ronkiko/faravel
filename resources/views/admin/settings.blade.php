<!-- v0.4.3 -->
<!-- resources/views/admin/settings.blade.php
Назначение: форма настроек троттлинга. Поля из AdminController@settings.
FIX: Добавлены поля кулдауна постинга:
     - post_cd_guest (сек; -1 = запрет)
     - post_cd_default (сек)
     - post_cd_groups (многострочно "group_id=seconds")
     Строгий Blade: только {{ ... }}, без функций, условий и PHP.
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

    <hr style="margin:18px 0; opacity:.35;">

    <h2 class="page-subtitle" style="margin:8px 0 6px;">Постинг: пауза между ответами</h2>
    <p class="muted" style="margin:0 0 12px;">
      Значение в секундах. <code>-1</code> — запрет. <code>0</code> — без лимита.
    </p>

    <div class="group" style="margin-bottom:10px;">
      <label for="post_cd_guest">Гость (group_id=0)</label>
      <input id="post_cd_guest" name="post_cd_guest" type="number" min="-1" max="86400"
             value="{{ $post_cd_guest }}">
    </div>

    <div class="group" style="margin-bottom:10px;">
      <label for="post_cd_default">По умолчанию для зарегистрированных</label>
      <input id="post_cd_default" name="post_cd_default" type="number" min="0" max="86400"
             value="{{ $post_cd_default }}">
    </div>

    <div class="group" style="margin-bottom:14px;">
      <label for="post_cd_groups">Переопределения по группам
        <span class="muted">(по одному правилу в строке, формат: <code>group_id=seconds</code>)</span>
      </label>
      <textarea id="post_cd_groups" name="post_cd_groups" rows="6">{{ $post_cd_groups }}</textarea>
    </div>

    <button class="btn" type="submit">Сохранить</button>
  </form>
@endsection
