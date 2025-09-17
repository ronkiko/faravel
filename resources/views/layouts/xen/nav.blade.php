<!-- v0.4.5 -->
<!-- resources/views/layouts/xen/nav.blade.php
Назначение: верхняя навигация темы Xen. Подсветка активного пункта строится
по строке layout.nav.active ∈ {home,forum,admin,mod}. Ссылки берутся из
layout.nav.links, права показа — из layout.nav.show, данные пользователя — из
layout.nav.auth. Совместимо со строгим Blade (никаких функций/тернариев).
-->
<nav class="xen-navbar">
  <input id="xnav" class="xen-nav__checkbox" type="checkbox" aria-label="Открыть меню">
  <label class="xen-burger" for="xnav" aria-hidden="true"></label>

  <div class="xen-menubar__inner">
    <div class="xen-menubar__left">
      <a
        class="xen-menubar__link @if($layout['nav']['active']==='home') is-active @endif"
        href="{{ $layout['nav']['links']['home'] }}"
      >Главная</a>

      <a
        class="xen-menubar__link @if($layout['nav']['active']==='forum') is-active @endif"
        href="{{ $layout['nav']['links']['forum'] }}"
      >Форум</a>

      @if($layout['nav']['show']['admin'])
        <a
          class="xen-menubar__link @if($layout['nav']['active']==='admin') is-active @endif"
          href="{{ $layout['nav']['links']['admin'] }}"
        >Админка</a>
      @endif

      @if($layout['nav']['show']['mod'])
        <a
          class="xen-menubar__link @if($layout['nav']['active']==='mod') is-active @endif"
          href="{{ $layout['nav']['links']['mod'] }}"
        >Модератор</a>
      @endif
    </div>

    <div class="xen-menubar__right">
      @if ($layout['nav']['auth']['is_auth'])
        <span class="xen-user">{{ $layout['nav']['auth']['username'] }}</span>
        <form method="POST" action="{{ $layout['nav']['links']['logout'] }}" class="xen-logout-form" style="display:inline;">
          <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
          <button type="submit" class="xen-menubar__link">Выйти</button>
        </form>
      @else
        <a class="xen-btn--login @if($layout['nav']['active']==='login') is-active @endif"
           href="{{ $layout['nav']['links']['login'] }}">Войти</a>
      @endif
    </div>
  </div>

  <aside class="xen-drawer" aria-hidden="true">
    <div class="xen-drawer__header">
      <div class="xen-drawer__title">Меню</div>
      <label class="xen-drawer__close" for="xnav" aria-label="Закрыть меню">&times;</label>
    </div>

    <div class="xen-drawer__section">
      <a class="@if($layout['nav']['active']==='home') is-active @endif"
         href="{{ $layout['nav']['links']['home'] }}">Главная</a>
      <a class="@if($layout['nav']['active']==='forum') is-active @endif"
         href="{{ $layout['nav']['links']['forum'] }}">Форум</a>
      @if($layout['nav']['show']['admin'])
        <a class="@if($layout['nav']['active']==='admin') is-active @endif"
           href="{{ $layout['nav']['links']['admin'] }}">Админ</a>
      @endif
      @if($layout['nav']['show']['mod'])
        <a class="@if($layout['nav']['active']==='mod') is-active @endif"
           href="{{ $layout['nav']['links']['mod'] }}">Модератор</a>
      @endif
    </div>

    <div class="xen-drawer__section">
      @if ($layout['nav']['auth']['is_auth'])
        <div class="xen-drawer__user">{{ $layout['nav']['auth']['username'] }}</div>
        <form method="POST" action="{{ $layout['nav']['links']['logout'] }}" class="xen-logout-form" style="display:inline;">
          <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
          <button type="submit" class="xen-linklike-btn">Выйти</button>
        </form>
      @else
        <a href="{{ $layout['nav']['links']['login'] }}">Войти</a>
        <a href="{{ $layout['nav']['links']['register'] }}">Регистрация</a>
      @endif
    </div>
  </aside>

  <label class="xen-scrim" for="xnav" aria-hidden="true"></label>
</nav>
