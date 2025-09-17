<!-- resources/views/layouts/flash.blade.php -->
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
