<!-- v0.4.121 -->
{{-- resources/views/forum/create_topic.blade.php
--}}
@extends('layouts.theme')

@push('styles')
  <style>
    .wrap{max-width:860px;margin:0 auto}
    .form{display:grid;gap:8px;margin-top:.75rem}
    .pill{display:inline-block;padding:.3rem .6rem;border:1px solid #e5ecf5;border-radius:999px}
    .muted{opacity:.75}
    .f-input--area{min-height:160px}
  </style>
@endpush

@section('content')
  <nav class="wrap" aria-label="Хлебные крошки">
    <a href="{{ $vm['links']['forum'] }}">Форум</a> <span class="muted">›</span>
    <a href="{{ $vm['links']['hub'] }}">{{ $vm['tag']['title'] }}</a>
    <span class="muted">›</span> Новая тема
  </nav>

  <header class="wrap" style="margin-top:.25rem">
    <h1 style="margin:0">Новая тема в «{{ $vm['tag']['title'] }}»</h1>
    <div class="muted">Категория: <span class="pill">{{ $vm['category']['title'] }}</span></div>
  </header>

  @if($vm['flash']['has_error'])
    <div class="wrap" style="margin-top:.5rem;color:#b91c1c">{{ $vm['flash']['error'] }}</div>
  @endif
  @if($vm['flash']['has_success'])
    <div class="wrap" style="margin-top:.5rem;color:#166534">{{ $vm['flash']['success'] }}</div>
  @endif

  <form class="wrap form" method="POST" action="{{ $vm['form']['action'] }}">
    <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
    <label class="f-label" for="title">Заголовок</label>
    <input class="f-input" id="title" name="title" type="text" value="{{ $vm['draft']['title'] }}">
    <label class="f-label" for="content">Текст</label>
    <textarea class="f-input f-input--area" id="content" name="content" placeholder="Текст сообщения...">{{ $vm['draft']['content'] }}</textarea>
    <div class="f-actions">
      <button class="f-btn f-btn--primary" type="submit">Создать тему</button>
      <a class="f-btn" href="{{ $vm['links']['hub'] }}">Отмена</a>
    </div>
  </form>
@endsection
