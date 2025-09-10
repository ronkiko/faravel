<!-- v0.4.2 -->
{{-- resources/views/auth/login.blade.php
Назначение: страница входа. «Немой» Blade: печатает форму и ссылки. Заголовок и форма
           центрируются единым блоком (.xen-card-wrap). Есть кнопка «Войти» и
           подсказка со ссылкой «зарегистрируйтесь».
FIX: Убраны инлайновые стили, добавлена кнопка submit и подсказка. Выравнивание по центру.
--}}
@extends('layouts.xen.theme')

@section('content')
  <div class="xen-card-wrap">
    <h1 style="margin:0 0 12px 0">Вход</h1>

    <form class="form" method="POST" action="/login" autocomplete="on"
          style="background:#fff;border:1px solid #d9dee8;border-radius:10px;padding:16px;
                 box-shadow:0 2px 8px rgba(0,0,0,.06);max-width:480px">
      <input type="hidden" name="_token" value="{{ $csrf_token ?? '' }}">
      <div class="group" style="margin-bottom:12px">
        <label for="login-username" style="display:block;margin-bottom:6px">Username</label>
        <input id="login-username" type="text" name="username" value=""
               autofocus
               style="width:100%;padding:10px;border:1px solid #d9dee8;border-radius:8px">
      </div>
      <div class="group" style="margin-bottom:16px">
        <label for="login-password" style="display:block;margin-bottom:6px">Password</label>
        <input id="login-password" type="password" name="password"
               style="width:100%;padding:10px;border:1px solid #d9dee8;border-radius:8px">
      </div>

      <button type="submit" class="xen-btn xen-btn--primary">Войти</button>

      <div class="xen-form-hint">
        Нет аккаунта?
        <a href="{{ $layout['nav']['links']['register'] }}">зарегистрируйтесь</a>.
      </div>
    </form>
  </div>
@endsection
