<!-- v0.1.0 — добавлена шапка версии; поведение без изменений -->
@extends('layouts.main_admin')

@section('admin_content')
    <h1 style="margin-top:0">Настройки троттлинга</h1>
    @include('layouts.flash')

    <form method="POST" action="/admin/settings" class="card" style="padding:14px;max-width:560px;">
        <input type="hidden" name="_token" value="{{ $layout['csrf'] }}">

        <label style="display:block;margin-bottom:8px;">
            <div>Окно (сек)</div>
            <input type="number" name="window_sec" min="1" max="3600"
                   value="<?= htmlspecialchars((string)($window_sec ?? 60), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label style="display:block;margin-bottom:8px;">
            <div>GET max</div>
            <input type="number" name="get_max" min="1" max="10000"
                   value="<?= htmlspecialchars((string)($get_max ?? 120), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label style="display:block;margin-bottom:8px;">
            <div>POST max</div>
            <input type="number" name="post_max" min="1" max="10000"
                   value="<?= htmlspecialchars((string)($post_max ?? 15), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label style="display:block;margin-bottom:8px;">
            <div>Session max</div>
            <input type="number" name="session_max" min="1" max="50000"
                   value="<?= htmlspecialchars((string)($session_max ?? 300), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <label style="display:block;margin-bottom:12px;">
            <div>Исключённые пути (CSV)</div>
            <input type="text" name="exempt_paths"
                   value="<?= htmlspecialchars((string)($exempt_paths ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>

        <button type="submit" class="btn">Сохранить</button>
    </form>
@endsection
