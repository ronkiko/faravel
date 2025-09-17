<!-- v0.4.118 -->
{{-- resources/views/mod/index.blade.php
Назначение: заглушка панели модератора.  Выводит приветственное
сообщение и информирует, что раздел модератора находится в разработке.
Использует базовую Xen‑тему через layouts.theme, чтобы визуально
соответствовать остальным страницам сайта.
--}}

@extends('layouts.theme')

@section('content')
  <div class="xen-card-wrap">
    <h1 style="margin:0 0 12px 0">Раздел модератора</h1>
    <div class="card" style="padding:16px;border:1px solid #d9dee8;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);max-width:600px">
      <p>Добро пожаловать в раздел модератора.  В будущем здесь появятся инструменты для модерирования форума.</p>
    </div>
  </div>
@endsection