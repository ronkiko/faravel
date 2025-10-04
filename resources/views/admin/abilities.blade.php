<!-- v0.4.3 -->
<!-- resources/views/admin/abilities.blade.php
Назначение: список способностей, сгруппированный по разделам. Группы — сворачиваемые.
FIX: Перестроен вывод на <details>/<summary> без JS. По умолчанию группы закрыты.
     Только базовые директивы Blade и вывод переменных/индексов.
-->
@extends('layouts.main_admin')

@section('admin_content')
  <h1 class="page-title">Abilities</h1>
  <p class="muted" style="margin:6px 0 16px;">
    Управление способностями и минимальными ролями доступа.
  </p>

  @if ($groups)
    @foreach ($groups as $g)
      <details class="table-group">
        <summary>
          <span class="info-title">{{ $g['title'] }}</span>
          <span class="badge">{{ $g['count'] }}</span>
        </summary>

        <div class="table-wrap">
          <table class="table table--compact table--hover">
            <thead>
              <tr>
                <th class="col-name">Name</th>
                <th>Label</th>
                <th class="col-role">Min&nbsp;Role</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($g['items'] as $a)
                <tr>
                  <td data-th="Name">{{ $a['name'] }}</td>
                  <td data-th="Label">{{ $a['label'] }}</td>
                  <td data-th="Min Role">{{ $a['min_role_label'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </details>
    @endforeach
  @else
    <div class="card"><div class="muted">Способностей пока нет.</div></div>
  @endif
@endsection
