<!-- v0.4.123 -->
<!-- resources/views/forum/topic.blade.php
Назначение: страница темы (строгий Blade). Без {!! !!} и Blade-комментариев.
FIX: Центровка и стили как на других страницах форума; вывод содержимого поста через
     безопасный {{ $p['content'] }}; добавлены «крошки», заголовок и форма ответа.
-->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/forum.css">
  <style>
    .wrap{max-width:980px;margin:0 auto}
    .items{display:grid;gap:12px;margin-top:.75rem}
    .row{padding:12px;border:1px solid #e5ecf5;border-radius:10px;background:#fff}
    .muted{opacity:.75}
    .f-actions{display:flex;gap:.5rem;flex-wrap:wrap}
    .post-body{white-space:pre-wrap}
  </style>
@endpush

@section('content')
  <nav class="wrap" aria-label="Хлебные крошки">
    <a href="/forum">Форум</a> <span class="muted">›</span>
    <a href="/forum/c/{{ $vm['topic']['category_slug'] }}/">{{ $vm['topic']['category_title'] }}</a>
    <span class="muted">›</span>
    <span class="muted">{{ $vm['topic']['title'] }}</span>
  </nav>

  <header class="wrap" style="margin-top:.5rem">
    <h1>{{ $vm['topic']['title'] }}</h1>
  </header>

  @if($vm['posts'])
    <section class="items wrap">
      @foreach($vm['posts'] as $p)
        <article class="row">
          <div><strong>{{ $p['user']['username'] }}</strong></div>
          <div class="muted">{{ $p['created_ago'] }}</div>
          <div class="post-body">{{ $p['content'] }}</div>
        </article>
      @endforeach
    </section>
  @else
    <div class="wrap muted" style="margin-top:.75rem">Сообщений пока нет.</div>
  @endif

  <div id="last" class="wrap"></div>

  @if($vm['can_reply'])
    <a id="reply"></a>
    <form class="wrap" method="POST" action="{{ $vm['links']['reply'] }}" style="margin-top:12px">
      <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">
      <label>Сообщение</label>
      <textarea class="f-input f-input--area" name="content" required
                style="display:block;width:100%;min-height:160px;margin-top:6px"></textarea>
      <div class="f-actions" style="margin-top:8px">
        <button class="f-btn f-btn--primary" type="submit">Отправить</button>
      </div>
    </form>
  @endif
@endsection
