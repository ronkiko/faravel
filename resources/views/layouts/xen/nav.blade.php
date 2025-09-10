<!-- v0.4.10 -->
{{-- resources/views/layouts/xen/nav.blade.php
Назначение: липкий навбар Xen. Мобайл: бургер слева, «Войти» справа, drawer без JS.
FIX: Добавлены шапка drawer'а с заголовком «Меню» и кнопкой-крестиком (label→checkbox),
     чтобы мобильное меню можно было закрыть. Остальной DOM без изменений.
--}}
<nav class="xen-navbar">
  <!-- CSS-only toggle -->
  <input id="xnav" class="xen-nav__checkbox" type="checkbox" aria-label="Открыть меню">

  <!-- Burger (visible on mobile) -->
  <label class="xen-burger" for="xnav" aria-hidden="true"></label>

  <!-- Inline menubar (desktop) -->
  <div class="xen-menubar__inner">
    <div class="xen-menubar__left">
      <a class="xen-menubar__link @if($layout['nav']['active']==='home') is-active @endif"
         href="{{ $layout['nav']['links']['home'] }}">Главная</a>

      <a class="xen-menubar__link @if($layout['nav']['active']==='forum') is-active @endif"
         href="{{ $layout['nav']['links']['forum'] }}">Форум</a>

      @if($layout['nav']['auth']['is_admin'])
        <a class="xen-menubar__link @if($layout['nav']['active']==='admin') is-active @endif"
           href="{{ $layout['nav']['links']['admin'] }}">Админка</a>
      @endif
    </div>

    <div class="xen-menubar__right">
      @if($layout['nav']['auth']['is_auth'])
        <span class="xen-user">{{ $layout['nav']['auth']['username'] }}</span>
        <a class="xen-menubar__link" href="{{ $layout['nav']['links']['logout'] }}">Выйти</a>
      @else
        <a class="xen-btn--login @if($layout['nav']['active']==='login') is-active @endif"
           href="{{ $layout['nav']['links']['login'] }}">Войти</a>
      @endif
    </div>
  </div>

  <!-- Drawer (mobile) -->
  <aside class="xen-drawer" aria-hidden="true">
    <div class="xen-drawer__header">
      <div class="xen-drawer__title">Меню</div>
      <label class="xen-drawer__close" for="xnav" aria-label="Закрыть меню">&times;</label>
    </div>

    <div class="xen-drawer__section">
      <a href="{{ $layout['nav']['links']['home'] }}">Главная</a>
      <a href="{{ $layout['nav']['links']['forum'] }}">Форум</a>
      @if($layout['nav']['auth']['is_admin'])
        <a href="{{ $layout['nav']['links']['admin'] }}">Админка</a>
      @endif
    </div>

    <div class="xen-drawer__section">
      @if($layout['nav']['auth']['is_auth'])
        <div class="xen-drawer__user">{{ $layout['nav']['auth']['username'] }}</div>
        <a href="{{ $layout['nav']['links']['logout'] }}">Выйти</a>
      @else
        <a href="{{ $layout['nav']['links']['login'] }}">Войти</a>
        <a href="{{ $layout['nav']['links']['register'] }}">Регистрация</a>
      @endif
    </div>
  </aside>

  <!-- Scrim: click to close -->
  <label class="xen-scrim" for="xnav" aria-hidden="true"></label>
</nav>
