<!-- v0.4.10 -->
{{-- resources/views/forum/topic.blade.php — v0.4.10
Назначение: страница темы. Строгий Blade: только {{ }} и директивы.
FIX: убраны @php/вызовы функций/asset_ver; вывод постов — чистый, без include.
--}}
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/forum.css">
  <style>
    .f-wrap{max-width:980px;margin:0 auto}
    .f-breadcrumb,.f-header{max-width:980px;margin-left:auto;margin-right:auto}
    .f-actions{display:flex;gap:.5rem;flex-wrap:wrap}
    .f-thread{display:grid;gap:14px;margin-top:.75rem}
    .f-form{max-width:980px;margin:12px auto 0;display:grid;gap:8px}
    .f-input--area{min-height:160px}
    .f-note,.f-alert{max-width:980px;margin:8px auto 0}
  </style>
@endpush

@section('content')
  <nav class="f-breadcrumb f-wrap" aria-label="Хлебные крошки">
    <a href="/forum">Форум</a> <span class="muted">›</span>
    <a href="/forum/c/{{ $vm['topic']['category_slug'] ?? '' }}/">{{ $vm['topic']['category_title'] ?? '' }}</a>
    <span class="muted">›</span>
    <span class="muted">{{ $vm['topic']['title'] ?? '' }}</span>
  </nav>

  <header class="f-header f-wrap">
    <h1 class="f-title" style="margin-bottom:0">{{ $vm['topic']['title'] ?? '' }}</h1>
    <div class="f-actions">
      <a class="f-btn" href="/forum">Назад</a>
      @if (!empty($vm['canReply']))
        @if (!empty($vm['topic']['slug']))
          <a class="f-btn f-btn--primary" href="/forum/t/{{ $vm['topic']['slug'] }}/reply">Ответить</a>
        @elseif (!empty($vm['topic']['id']))
          <a class="f-btn f-btn--primary" href="/forum/t/{{ $vm['topic']['id'] }}/reply">Ответить</a>
        @endif
      @endif
    </div>
  </header>

  @if (!empty($vm['warn']))
    <div class="f-alert f-alert--error">{{ $vm['warn'] }}</div>
  @endif

  @if (!empty($vm['posts']))
    <div class="f-thread f-wrap">
      @foreach ($vm['posts'] as $p)
        <article class="fp" id="p-{{ $p['id'] }}">
          <header class="fp-head">
            <a href="{{ $p['author']['profile_url'] ?? '#' }}" class="fp-user">
              <img src="{{ $p['author']['avatar_url'] ?? '' }}" alt="" class="fp-ava">
              <span class="fp-name">{{ $p['author']['name'] ?? 'User' }}</span>
            </a>
            <time datetime="{{ $p['created_iso'] ?? '' }}">{{ $p['created_human'] ?? '' }}</time>
          </header>
          <div class="fp-body">
            @raw($p['content_html'])
          </div>
          <footer class="fp-foot">
            <span class="fp-metric">Rep: {{ $p['metrics']['rep'] ?? 0 }}</span>
            <span class="fp-metric">Posts: {{ $p['metrics']['posts'] ?? 0 }}</span>
            <span class="fp-metric">Days: {{ $p['metrics']['tenure_days'] ?? 0 }}</span>
          </footer>
        </article>
      @endforeach
    </div>
  @else
    <div class="f-note f-wrap">Нет сообщений.</div>
  @endif

  {{-- Анкор в конец, для скролла --}}
  <div id="last" class="f-wrap"></div>

  @if (!empty($vm['canReply']))
    <a id="reply"></a>
    @if (!empty($vm['topic']['slug']))
      <form class="f-form" method="POST" action="/forum/t/{{ $vm['topic']['slug'] }}/reply">
    @elseif (!empty($vm['topic']['id']))
      <form class="f-form" method="POST" action="/forum/t/{{ $vm['topic']['id'] }}/reply">
    @else
      <form class="f-form" method="POST" action="">
    @endif
      <input type="hidden" name="_token" value="{{ $vm['base']['csrf'] ?? '' }}">
      <label class="f-label">Сообщение</label>
      <textarea class="f-input f-input--area" name="content" required></textarea>
      <div class="f-actions">
        <button class="f-btn f-btn--primary" type="submit">Отправить</button>
      </div>
    </form>
  @endif
@endsection
