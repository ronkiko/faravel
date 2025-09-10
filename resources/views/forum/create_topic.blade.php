{{-- resources/views/forum/create_topic.blade.php — v0.1.0
Назначение: форма создания темы из выбранного хаба (тега). Без логики и БД.
FIX: предзаполнены категория и тег; POST на /forum/f/{slug}/create.
--}}
@extends('layouts.theme')

@php
  $vm = is_array($vm ?? null) ? $vm : [];
  $tag = $vm['tag'] ?? [];
  $cat = $vm['category'] ?? [];
  $postUrl = (string)($vm['postUrl'] ?? '');
  $e = static fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  $title = (string)($vm['draftTitle'] ?? '');
  $content = (string)($vm['draftContent'] ?? '');
@endphp

@push('styles')
  <style>
    .wrap{max-width:860px;margin:0 auto}
    .form{display:grid;gap:8px;margin-top:.75rem}
    .pill{display:inline-block;padding:.3rem .6rem;border:1px solid #e5ecf5;border-radius:999px}
    .muted{opacity:.75}
  </style>
@endpush

@section('content')
  <nav class="wrap" aria-label="Хлебные крошки">
    <a href="/forum">Форум</a> <span class="muted">›</span>
    <a href="/forum/f/{{ $e((string)($tag['slug'] ?? '')) }}/">{{ $e((string)($tag['title'] ?? 'Тег')) }}</a>
    <span class="muted">›</span> Новая тема
  </nav>

  <header class="wrap" style="margin-top:.25rem">
    <h1 style="margin:0">Новая тема в «{{ $e((string)($tag['title'] ?? '')) }}»</h1>
    <div class="muted">
      Категория: <span class="pill">{{ $e((string)($cat['title'] ?? '')) }}</span>
    </div>
  </header>

  @if (session('error'))
    <div class="wrap" style="margin-top:.5rem;color:#b91c1c">{{ $e((string)session('error')) }}</div>
  @endif
  @if (session('success'))
    <div class="wrap" style="margin-top:.5rem;color:#166534">{{ $e((string)session('success')) }}</div>
  @endif

  <form class="wrap form" method="POST" action="{{ $e($postUrl) }}">
    <input type="hidden" name="_token" value="{{ $e(csrf_token()) }}">
    <label class="f-label" for="title">Заголовок</label>
    <input class="f-input" id="title" name="title" type="text" value="{{ $e($title) }}">
    <label class="f-label" for="content">Текст</label>
    <textarea class="f-input f-input--area" id="content" name="content" placeholder="Текст сообщения...">{{ $e($content) }}</textarea>
    <div class="f-actions">
      <button class="f-btn f-btn--primary" type="submit">Создать тему</button>
      <a class="f-btn" href="/forum/f/{{ $e((string)($tag['slug'] ?? '')) }}/">Отмена</a>
    </div>
  </form>
@endsection
