<?php
/* tools/lint/BladePurityGuard.php v0.1.1
Назначение: CLI-линтер чистоты Blade. Запрещает бизнес-логику в шаблонах:
блокирует DB, прямые фасады БД, и встроенный JS (onerror, <script>) в
resources/views.
FIX: корректно извлекаем int-смещение из preg_match_all через распаковку
[$match,$offset]; добавлено явное (int) для IDE/линтеров. */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$views = $root . '/resources/views';

$exclude = [
    $root . '/resources/legacy',
];

$banned = [
    ['~\\bDB::~', 'DB:: в шаблоне'],
    ['~Faravel\\\\Support\\\\Facades\\\\DB~', 'use Faravel\\Support\\Facades\\DB в шаблоне'],
];

$jsWarn = [
    ['~onerror\\s*=~i', 'inline onerror (JS)'],
    ['~<script\\b~i', '<script> (JS)'],
];

$allowScript = getenv('ALLOW_SCRIPT_TAGS') === '1';

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)
);

$violations = [];
$warnings = [];

foreach ($it as $spl) {
    $path = $spl->getPathname();
    if (!preg_match('~\\.blade\\.php$~', $path)) continue;

    $skip = false;
    foreach ($exclude as $ex) {
        if (str_starts_with($path, $ex)) { $skip = true; break; }
    }
    if ($skip) continue;

    $txt = @file_get_contents($path);
    if ($txt === false) continue;
    $content = $txt;

    // hard violations
    foreach ($banned as [$re, $label]) {
        if (preg_match_all($re, $content, $mm, PREG_OFFSET_CAPTURE)) {
            foreach ($mm[0] as $m) {
                [$match, $offset] = $m;
                $offset = (int)$offset;
                $line = substr_count($content, "\n", 0, $offset) + 1;
                $snippet = trim(substr($content, max(0, $offset - 40), 80));
                $violations[] = [
                    'path'=>$path,'line'=>$line,'label'=>$label,'snippet'=>$snippet
                ];
            }
        }
    }

    // warnings (JS)
    foreach ($jsWarn as [$re, $label]) {
        if ($allowScript && $label === '<script> (JS)') continue;
        if (preg_match_all($re, $content, $mm, PREG_OFFSET_CAPTURE)) {
            foreach ($mm[0] as $m) {
                [$match, $offset] = $m;
                $offset = (int)$offset;
                $line = substr_count($content, "\n", 0, $offset) + 1;
                $snippet = trim(substr($content, max(0, $offset - 40), 80));
                $warnings[] = [
                    'path'=>$path,'line'=>$line,'label'=>$label,'snippet'=>$snippet
                ];
            }
        }
    }
}

if ($violations || $warnings) {
    $out = fopen('php://stdout', 'w');
    if ($violations) {
        fwrite($out, "BladePurityGuard: НАРУШЕНИЯ (запрещено):\n");
        foreach ($violations as $v) {
            fwrite($out, sprintf(
                "- %s:%d  [%s]  %s\n", rel($v['path'],$root), $v['line'], $v['label'], $v['snippet']
            ));
        }
    }
    if ($warnings) {
        fwrite($out, "BladePurityGuard: ПРЕДУПРЕЖДЕНИЯ:\n");
        foreach ($warnings as $w) {
            fwrite($out, sprintf(
                "- %s:%d  [%s]  %s\n", rel($w['path'],$root), $w['line'], $w['label'], $w['snippet']
            ));
        }
        fwrite($out, "Подсказка: экспорт ALLOW_SCRIPT_TAGS=1 отключит проверку <script>.\n");
    }
    exit($violations ? 1 : 0);
}

fwrite(STDOUT, "BladePurityGuard: OK — нарушений не найдено.\n");
exit(0);

// helpers
function rel(string $path, string $root): string {
    return str_starts_with($path, $root) ? substr($path, strlen($root)+1) : $path;
}
