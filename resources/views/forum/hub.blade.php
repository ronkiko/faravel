<!-- v0.4.3 -->
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
    .actions{display:flex;gap:8px;align-items:center;margin-top:.5rem}
    .btn{display:inline-block;padding:.4rem .7rem;border-radius:8px;border:1px solid #d9e0ea;
         text-decoration:none}
  </style>
@endpush

@section('content')
  <div class="wrap" style="margin-top:.5rem">
    <div class="actions">
      <a class="pill" href="{{ $vm['links']['sort_last'] }}">Последние</a>
      <a class="pill" href="{{ $vm['links']['sort_new'] }}">Новые</a>
      <a class="pill" href="{{ $vm['links']['sort_posts'] }}">По постам</a>

      @if($vm['show_create'])
        <a class="btn" href="{{ $vm['create_url'] }}">Создать тему</a>
      @endif
    </div>
  </div>

  @if(!empty($vm['topics']))
    <div class="wrap items" aria-label="Список тем">
      @foreach($vm['topics'] as $t)
        <div class="row">
          <div><a href="{{ $t['url'] }}">{{ $t['title'] }}</a></div>
          <div class="muted">
            {{ $t['when'] }}
            @if(!empty($t['author'])) • {{ $t['author'] }} @endif
            @if(isset($t['posts'])) • {{ $t['posts'] }} постов @endif
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="wrap muted">Тем пока нет.</div>
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
