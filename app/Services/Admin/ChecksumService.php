<?php // v0.4.117
/* app/Services/Admin/ChecksumService.php
Purpose: Сервис для вычисления контрольных сумм файлов и сравнения их с ранее
         сохранённым состоянием. Предоставляет методы генерации дерева
         хешей, сравнения с сохранёнными значениями и шифрования/дешифрования
         результата. Используется в админ‑модуле checksum.
FIX: Новый класс. Инкапсулирует операции с файловой системой и простым
     симметричным шифрованием (XOR+base64) по ключу админа.
*/

namespace App\Services\Admin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ChecksumService
{
    /**
     * Построить дерево контрольных сумм для указанной директории.
     * Возвращает ассоциативный массив: [ 'file.php' => 'hash', 'subdir' => [...] ].
     *
     * @param string $dir Абсолютный путь к каталогу.
     * @param string $base Относительный корень для ключей.
     * @return array<string,mixed>
     */
    public static function buildTree(string $dir, string $base = ''): array
    {
        $result = [];
        $dh = @opendir($dir);
        if ($dh === false) {
            return $result;
        }
        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
            $rel  = $base === '' ? $entry : $base . '/' . $entry;
            if (is_dir($path)) {
                $result[$entry] = self::buildTree($path, $rel);
            } elseif (is_file($path)) {
                $hash = hash_file('sha256', $path) ?: '';
                $result[$entry] = $hash;
            }
        }
        closedir($dh);
        ksort($result);
        return $result;
    }

    /**
     * Шифровать строку простым XOR с ключом и кодировать base64.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    public static function encrypt(string $data, string $key): string
    {
        $keyBytes = md5($key, true);
        $out = '';
        $kLen = strlen($keyBytes);
        $dLen = strlen($data);
        for ($i = 0; $i < $dLen; $i++) {
            $out .= $data[$i] ^ $keyBytes[$i % $kLen];
        }
        return base64_encode($out);
    }

    /**
     * Дешифровать base64‑закодированную строку, расшифровав XOR с ключом.
     * Возвращает null в случае ошибки декодирования.
     *
     * @param string $data
     * @param string $key
     * @return string|null
     */
    public static function decrypt(string $data, string $key): ?string
    {
        $raw = base64_decode($data, true);
        if ($raw === false) {
            return null;
        }
        $keyBytes = md5($key, true);
        $out = '';
        $kLen = strlen($keyBytes);
        $dLen = strlen($raw);
        for ($i = 0; $i < $dLen; $i++) {
            $out .= $raw[$i] ^ $keyBytes[$i % $kLen];
        }
        return $out;
    }

    /**
     * Вычислить различия между двумя деревьями контрольных сумм.
     * Возвращает массив вида [path => 'same'|'changed'|'new'|'removed'].
     *
     * @param array<string,mixed> $current
     * @param array<string,mixed> $saved
     * @param string              $prefix
     * @return array<string,string>
     */
    public static function diffTrees(array $current, array $saved, string $prefix = ''): array
    {
        $diff = [];
        // Check current files/dirs
        foreach ($current as $name => $value) {
            $path = $prefix === '' ? $name : $prefix . '/' . $name;
            if (is_array($value)) {
                // directory
                $savedSub = isset($saved[$name]) && is_array($saved[$name]) ? $saved[$name] : [];
                $subDiff = self::diffTrees($value, $savedSub, $path);
                $diff = $diff + $subDiff;
            } else {
                // file
                if (!isset($saved[$name])) {
                    $diff[$path] = 'new';
                } elseif (!is_string($saved[$name]) || $saved[$name] !== $value) {
                    $diff[$path] = 'changed';
                } else {
                    $diff[$path] = 'same';
                }
            }
        }
        // Check removed files
        foreach ($saved as $name => $value) {
            if (!isset($current[$name])) {
                $path = $prefix === '' ? $name : $prefix . '/' . $name;
                if (is_array($value)) {
                    // Mark all nested removed
                    $flatten = self::flatten($value, $path);
                    foreach ($flatten as $p) {
                        $diff[$p] = 'removed';
                    }
                } else {
                    $diff[$path] = 'removed';
                }
            }
        }
        return $diff;
    }

    /**
     * Flatten directory tree into list of file paths (for removed directories).
     *
     * @param array<string,mixed> $tree
     * @param string              $prefix
     * @return array<int,string>
     */
    private static function flatten(array $tree, string $prefix): array
    {
        $out = [];
        foreach ($tree as $name => $value) {
            $path = $prefix . '/' . $name;
            if (is_array($value)) {
                $out = array_merge($out, self::flatten($value, $path));
            } else {
                $out[] = $path;
            }
        }
        return $out;
    }
}