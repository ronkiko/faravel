<!-- resources/views/forum/hub.blade.php -->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/forum.css">
  <style>
    .wrap{max-width:980px;margin:0 auto}
    .items{display:grid;gap:8px;margin-top:.75rem}
    .row{padding:10px 12px;border:1px solid #e5ecf5;border-radius:10px;background:#fff}
    .muted{opacity:.75}
    .pag{display:flex;gap:6px;align-items:center;margin:10px 0}
    .pill{display:inline-block;padding:.25rem .5rem;border:1px solid #e5ecf5;border-radius:999px;
          margin:.15rem .25rem 0 0;font-size:.85rem;text-decoration:none}
  </style>
@endpush

@section('content')
  <div class="wrap" style="margin-top:.5rem">
    <a class="pill" href="{{ $vm['links']['sort_last'] }}">Последние</a>
    <a class="pill" href="{{ $vm['links']['sort_new'] }}">Новые</a>
    <a class="pill" href="{{ $vm['links']['sort_posts'] }}">По постам</a>
  </div>

  @if($vm['has_topics'])
    <div class="items wrap">
      @foreach($vm['topics'] as $t)
        <article class="row">
          <a href="{{ $t['url'] }}">{{ $t['title'] }}</a>
          <div class="muted">Постов: {{ $t['posts_count'] }} · Активность: {{ $t['when'] }}</div>
        </article>
      @endforeach
    </div>
  @else
    <div class="wrap muted" style="margin-top:.75rem">Тем пока нет.</div>
  @endif

  @if($vm['pager']['has_pages'])
    <nav class="wrap pag" aria-label="Навигация страниц">
      @if($vm['pager']['has_prev'])
        <a class="pill" href="{{ $vm['links']['prev'] }}">← Назад</a>
      @endif
      <span class="muted">Стр. {{ $vm['pager']['page'] }} из {{ $vm['pager']['pages'] }}</span>
      @if($vm['pager']['has_next'])
        <a class="pill" href="{{ $vm['links']['next'] }}">Вперёд →</a>
      @endif
    </nav>
  @endif
@endsection
