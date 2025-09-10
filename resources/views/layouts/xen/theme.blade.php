<!-- v0.4.15 -->
{{-- resources/views/layouts/xen/theme.blade.php
Назначение: базовый лейаут темы Xen. Рендерит верхний баннер сайта (site),
липкий navbar, флеш-сообщения, контент и футер. Строгий «немой» Blade.
FIX: Переведён с legacy brand.* на обязательный контракт site.*
(site.logo.url, site.home.url, site.title). DOM сохранён под текущий CSS
(.xen-brandbar*), чтобы не ломать стили.
--}}
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $layout['title'] }}</title>
  <link rel="stylesheet" href="/style/index.css">
</head>
<body class="xen">
  <header class="xen-header">
    <div class="xen-brandbar">
      <div class="xen-brandbar__inner">
        <a class="xen-brand" href="{{ $layout['site']['home']['url'] }}" aria-label="Home">
          <img class="xen-brand__logo"
               src="{{ $layout['site']['logo']['url'] }}"
               alt="{{ $layout['site']['title'] }}">
          <span class="xen-brand__text">{{ $layout['site']['title'] }}</span>
        </a>
      </div>
    </div>
  </header>

  {{-- Sticky navbar (данные из LayoutVM/LayoutService) --}}
  @include('layouts.xen.nav', ['layout' => $layout])

  <main class="xen-main">
    @include('layouts.flash', ['flash' => $flash])
    @yield('content')
  </main>

  <footer class="xen-footer">
    <div class="xen-footer__inner">© Faravel</div>
  </footer>
</body>
</html>
