<?php // v0.4.1
/* app/Support/ViewModelFactory.php
Purpose: Centralized, container-friendly factory to instantiate VMs via ArrayBuildable contract.
         Controllers pass named data, factory calls VM::fromArray(...).
FIX: New service to avoid positional-arg mismatches at controller layer.
*/
namespace App\Support;

use App\Contracts\ViewModel\ArrayBuildable;
use App\Contracts\ViewModel\ViewModelContract;

final class ViewModelFactory
{
    /**
     * Create a VM by class-name using named data.
     *
     * @template T of ViewModelContract
     * @param class-string<T> $vmClass
     * @param array<string,mixed> $data
     * @return T
     * @throws \InvalidArgumentException if $vmClass does not implement ArrayBuildable
     */
    public function make(string $vmClass, array $data): ViewModelContract
    {
        if (!is_subclass_of($vmClass, ArrayBuildable::class)) {
            throw new \InvalidArgumentException("$vmClass must implement ArrayBuildable");
        }
        /** @var class-string<ArrayBuildable&ViewModelContract> $vmClass */
        return $vmClass::fromArray($data);
    }
}
