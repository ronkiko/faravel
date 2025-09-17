<!-- v0.4.121 -->
{{-- resources/views/forum/index.blade.php
Назначение: главная форума — список категорий (строгий Blade).
FIX: никаких ??/функций; вывод только подготовленных данных VM.
--}}
@extends('layouts.xen.theme')

@section('content')
  <div class="f-wrap" style="max-width:1000px;margin:18px auto;padding:0 12px">
    <h1>{{ $vm['title'] }}</h1>

    @if($vm['has_categories'])
      <ul>
        @foreach($vm['categories'] as $c)
          <li><a href="{{ $c['url'] }}">{{ $c['title'] }}</a></li>
        @endforeach
      </ul>
    @else
      <p>Категории не найдены.</p>
    @endif
  </div>
@endsection
