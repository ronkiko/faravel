<?php // v0.4.1
/* app/Http/ViewModels/Base/BaseVM.php
Purpose: Common helpers for ViewModels: safe getters and required-keys check.
FIX: Initial helper class added. No state, only reusable utils.
*/
namespace App\Http\ViewModels\Base;

abstract class BaseVM
{
    /**
     * Safe array getter for nested arrays.
     *
     * @param array<string,mixed> $data
     * @param string $key
     * @return array<string,mixed>
     */
    protected static function arr(array $data, string $key): array
    {
        $v = $data[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    /**
     * Safe string getter.
     *
     * @param array<string,mixed> $data
     * @param string $key
     * @return string
     */
    protected static function str(array $data, string $key): string
    {
        return (string)($data[$key] ?? '');
    }

    /**
     * Ensure that required keys exist in the provided data bag.
     *
     * @param array<string,mixed> $data
     * @param array<int,string> $required
     * @throws \InvalidArgumentException if a required key is missing
     */
    protected static function requireKeys(array $data, array $required): void
    {
        foreach ($required as $k) {
            if (!array_key_exists($k, $data)) {
                throw new \InvalidArgumentException("Missing required key: {$k}");
            }
        }
    }
}
