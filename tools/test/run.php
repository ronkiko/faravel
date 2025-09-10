<?php
/* tools/test/run.php — v0.1.1
Назначение: минимальный раннер тестов Faravel без vendor. Загружает ядро через
bootstrap/app.php, рекурсивно находит *Test.php в tests/, выполняет функции
test__*. Код выхода 1 при фейлах.
FIX: убран require vendor/autoload.php; добавлен bootstrap/app.php и рекурсивный
поиск тестов через SPL.
*/

declare(strict_types=1);

// Бутстрап приложения Faravel
$ROOT = dirname(__DIR__, 2);
@ini_set('display_errors','1');
@date_default_timezone_set('UTC');

$app = require $ROOT.'/bootstrap/app.php'; // регистрирует автолоадеры и провайдеры

// Сбор тестов
$testsDir = $ROOT.'/tests';
$files = [];
if (is_dir($testsDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $spl) {
        $path = $spl->getPathname();
        if (preg_match('~/[^/]+Test\.php$~', $path)) {
            $files[] = $path;
        }
    }
}

echo "Faravel test runner\n";
foreach ($files as $file) {
    require $file;
}

// Ассерты
function assert_true($cond, string $msg=''): void {
    if (!$cond) throw new RuntimeException($msg === '' ? 'assert_true failed' : $msg);
}
function assert_eq($a,$b,string $msg=''): void {
    if ($a !== $b) {
        $da = var_export($a,true); $db = var_export($b,true);
        throw new RuntimeException($msg === '' ? "assert_eq failed\n{$da}\n!=\n{$db}" : $msg);
    }
}

// Запуск функций test__*
$defs  = get_defined_functions()['user'] ?? [];
$cases = array_values(array_filter($defs, static fn($f)=> str_starts_with($f, 'test__')));

$ok=0; $fail=0; $skip=0;
foreach ($cases as $fn) {
    try {
        $fn(); echo ".";
        $ok++;
    } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'SKIP:')) { echo "s"; $skip++; }
        else { echo "F"; $fail++; fwrite(STDERR, "\nFAIL {$fn}: ".$e->getMessage()."\n"); }
    } catch (Throwable $e) {
        echo "F"; $fail++; fwrite(STDERR, "\nFAIL {$fn}: ".$e->getMessage()."\n");
    }
}
echo "\nOK={$ok} FAIL={$fail} SKIP={$skip}\n";
exit($fail ? 1 : 0);
