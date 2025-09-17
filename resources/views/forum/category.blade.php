<!-- resources/views/forum/category.blade.php -->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/forum.css">
  <style>
    .f-wrap{max-width:980px;margin:18px auto;padding:0 10px}
    .f-hubs{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .f-hub{display:inline-block;border:1px solid #d9e0ea;border-radius:999px;padding:6px 10px;font-size:14px}
    .f-hub--muted{opacity:.6}
    .f-hub a{text-decoration:none}
    .f-hub a:hover{text-decoration:underline}
    .f-desc{margin-top:6px;color:#445}
  </style>
@endpush

@section('content')
<div class="f-wrap">
  <div class="f-breadcrumb"><a href="/forum/">Форум</a></div>

  <h1>{{ $vm['category']['title'] }}</h1>

  @if($vm['category']['has_description'])
    <div class="f-desc">{{ $vm['category']['description'] }}</div>
  @endif

  @if($vm['has_hubs'])
    <div class="f-hubs">
      @foreach($vm['hubs'] as $h)
        <div class="{{ $h['css_class'] }}">
          <a href="{{ $h['url'] }}">{{ $h['title'] }}</a>
        </div>
      @endforeach
    </div>
  @else
    <p>В этой категории пока нет хабов.</p>
  @endif
</div>
@endsection
