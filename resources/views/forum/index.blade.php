<!-- v0.4.122 -->
{{-- resources/views/forum/index.blade.php
Назначение: главная форума — список категорий (строгий Blade).
FIX: выводим description для каждой категории; семантика: заголовок + краткое описание.
--}}
@extends('layouts.xen.theme')

@section('content')
  <div class="f-wrap" style="max-width:1000px;margin:18px auto;padding:0 12px">
    <h1>{{ $vm['title'] }}</h1>

    @if($vm['has_categories'])
      <ul class="forum-category-list">
        @foreach($vm['categories'] as $c)
          <li class="forum-category-item" style="margin:10px 0;">
            <div class="forum-category-title">
              <a href="{{ $c['url'] }}">{{ $c['title'] }}</a>
            </div>
            @if($c['description'] !== '')
              <div class="forum-category-desc" style="font-size:0.95em;color:#555;">
                {{ $c['description'] }}
              </div>
            @endif
          </li>
        @endforeach
      </ul>
    @else
      <p>Категории не найдены.</p>
    @endif
  </div>
@endsection
