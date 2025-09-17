<?php // v0.4.117
/* app/Services/Admin/ContractChecker.php
Purpose: Проверка соблюдения контрактов в классах. Сканирует указанные каталоги,
         извлекает список декларируемых методов из шапки файла (блок `Contract:`)
         и сравнивает его с фактическими публичными методами класса. Возвращает
         подробные данные о несоответствиях для отображения в SafeMode‑админке.
FIX: Новая служба для админки. Реализует статический метод check() для
     единовременной проверки всех файлов. Используется в модуле admin/_stack.php.
*/

namespace App\Services\Admin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ContractChecker
{
    /**
     * Проверить соответствие контрактов для всех PHP‑файлов внутри указанных
     * директорий.
     *
     * @param array<string> $dirs Список абсолютных директорий для сканирования.
     * @return array<string,array{
     *   declared: array<int,string>,
     *   actual: array<int,string>,
     *   missing: array<int,string>,
     *   extra: array<int,string>
     * }>
     */
    public static function check(array $dirs): array
    {
        $results = [];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $path = $file->getPathname();
                $rel  = self::relativePath($path, $dir);
                $content = @file_get_contents($path);
                if ($content === false) {
                    continue;
                }
                // Ищем объявление контракта в блоке комментария.
                $declared = [];
                if (preg_match('/Contract:(.*?)\*\//s', $content, $m)) {
                    $block = $m[1];
                    $lines = preg_split("/\r?\n/", (string)$block);
                    foreach ($lines as $line) {
                        if (preg_match('/-\s*([A-Za-z0-9_]+)\s*\(/', $line, $mm)) {
                            $declared[] = $mm[1];
                        }
                    }
                }
                // Ищем фактические публичные методы (без static). Не полагаемся на
                // Reflection, так как классы могут не загружаться.
                $actual = [];
                if (preg_match_all('/\bpublic\s+function\s+([A-Za-z0-9_]+)\s*\(/', $content, $matches)) {
                    $actual = $matches[1];
                }
                // Сравниваем. Порядок и дубликаты несущественны.
                $missing = array_values(array_diff($declared, $actual));
                $extra   = array_values(array_diff($actual, $declared));
                $results[$path] = [
                    'declared' => $declared,
                    'actual'   => $actual,
                    'missing'  => $missing,
                    'extra'    => $extra,
                ];
            }
        }
        return $results;
    }

    /**
     * Получить относительный путь файла относительно базового каталога.
     *
     * @param string $file Абсолютный путь к файлу.
     * @param string $base Абсолютный базовый путь.
     * @return string
     */
    private static function relativePath(string $file, string $base): string
    {
        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        if (strpos($file, $base) === 0) {
            return substr($file, strlen($base));
        }
        return $file;
    }
}