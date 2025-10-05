<!-- resources/views/forum/category.blade.php -->
@extends('layouts.theme')

@push('styles')
  <link rel="stylesheet" href="/style/forum.css">
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
