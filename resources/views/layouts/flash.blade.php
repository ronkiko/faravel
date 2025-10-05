<!-- v0.4.3 -->
<!-- resources/views/layouts/flash.blade.php
Назначение: вывод флеш-сообщений как фиксированных «тостов» поверх контента.
FIX: Вместо «в потоке» — фиксированный слой .toast-layer с анимацией появления
     и авто-скрытия через ~4s. Оба типа сообщений (error/success) рендерятся,
     пустые контейнеры скрываются через :empty. Без JS, только CSS.
-->
<div class="toast-layer">
  @if ($flash['error'])
    <div class="toast toast--err">
      @foreach ($flash['error'] as $m)
        <div class="toast__row">{{ $m }}</div>
      @endforeach
    </div>
  @endif

  @if ($flash['success'])
    <div class="toast toast--ok">
      @foreach ($flash['success'] as $m)
        <div class="toast__row">{{ $m }}</div>
      @endforeach
    </div>
  @endif
</div>
