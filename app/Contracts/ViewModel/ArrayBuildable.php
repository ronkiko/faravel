<?php // v0.4.1
/* app/Contracts/ViewModel/ArrayBuildable.php
Purpose: Standard factory contract — build any VM from an associative array with named keys.
         This eliminates positional-arg mistakes in controllers.
FIX: New 'fromArray' contract added.
*/
namespace App\Contracts\ViewModel;

interface ArrayBuildable
{
    /**
     * Create VM from associative data (named keys). Each VM documents its expected keys.
     *
     * @param array<string,mixed> $data  Named data bag expected by the concrete VM.
     * @return static
     */
    public static function fromArray(array $data): static;
}
