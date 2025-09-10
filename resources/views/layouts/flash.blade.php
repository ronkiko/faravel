<!-- v0.4.1 -->
{{-- resources/views/layouts/flash.blade.php
Назначение: немой вывод флеш-сообщений под строгий Blade. Ожидает данные,
собранные во VM/контроллере: $flash = array{success:array<int,string>, error:array<int,string>}.
FIX: Убраны вызовы функций (empty/count), используем только булевы проверки массивов
по контракту FlashVM. Полное соответствие строгому Blade.
--}}
@if ($flash['success'])
  <div class="flash flash--ok">
    @foreach ($flash['success'] as $m)
      <div class="flash__row">{{ $m }}</div>
    @endforeach
  </div>
@endif

@if ($flash['error'])
  <div class="flash flash--err">
    @foreach ($flash['error'] as $m)
      <div class="flash__row">{{ $m }}</div>
    @endforeach
  </div>
@endif
