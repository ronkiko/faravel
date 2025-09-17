<!-- v0.4.3 -->
{{-- resources/views/auth/register.blade.php
Назначение: страница регистрации. «Немой» Blade. Заголовок и форма центрируются
           одним блоком (.xen-card-wrap). Кнопка «Зарегистрироваться» и рядом
           компактная подсказка «Уже есть аккаунт? войдите».
FIX: Центрирование по аналогии со страницей входа. Кнопка submit и inline-подсказка.
--}}
@extends('layouts.xen.theme')

@section('content')
  <div class="xen-card-wrap">
    <h1 style="margin:0 0 12px 0">Регистрация</h1>

    <form class="form" method="POST" action="/register" autocomplete="on"
          style="background:#fff;border:1px solid #d9dee8;border-radius:10px;padding:16px;
                 box-shadow:0 2px 8px rgba(0,0,0,.06);max-width:480px">
      <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

      <div class="group" style="margin-bottom:12px">
        <label for="reg-username" style="display:block;margin-bottom:6px">Username</label>
        <input id="reg-username" type="text" name="username"
               style="width:100%;padding:10px;border:1px solid #d9dee8;border-radius:8px">
      </div>

      <div class="group" style="margin-bottom:12px">
        <label for="reg-password" style="display:block;margin-bottom:6px">Password</label>
        <input id="reg-password" type="password" name="password"
               style="width:100%;padding:10px;border:1px solid #d9dee8;border-radius:8px">
      </div>

      <div class="group" style="margin-bottom:8px">
        <label for="reg-password2" style="display:block;margin-bottom:6px">Confirm password</label>
        <input id="reg-password2" type="password" name="password2"
               style="width:100%;padding:10px;border:1px solid #d9dee8;border-radius:8px">
      </div>

      <div class="xen-form-actions">
        <button type="submit" class="xen-btn xen-btn--primary">Зарегистрироваться</button>
        <div class="xen-form-hint xen-form-hint--inline">
          Уже есть аккаунт?
          <a href="{{ $layout['nav']['links']['login'] }}">войдите</a>.
        </div>
      </div>
    </form>
  </div>
@endsection
