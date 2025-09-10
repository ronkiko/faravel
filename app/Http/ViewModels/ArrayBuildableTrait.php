<?php // v0.4.1
/* app/Support/ViewModel/ArrayBuildableTrait.php
Purpose: Provide a standard static fromArray(array $data): static implementation for VMs.
         Hydrates public properties from the provided array safely.
FIX: Initial addition — reusable implementation to make VMs contract-compliant quickly.
*/

namespace App\Support\ViewModel;

/**
 * Reusable implementation for ArrayBuildable::fromArray().
 *
 * Использование в VM:
 *  class FooVM implements ArrayBuildable {
 *      use ArrayBuildableTrait;
 *      public string $title = '';
 *      public int $page = 1;
 *      // ...
 *  }
 */
trait ArrayBuildableTrait
{
    /**
     * Build ViewModel from a plain array by hydrating public props with matching keys.
     *
     * Preconditions: $data is a plain associative array. Unknown keys are ignored.
     * Side effects: none.
     *
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        /** @var static $vm */
        $vm = new static();

        // Reflect public properties only; ignore dynamic/magic.
        $rp = new \ReflectionObject($vm);
        foreach ($rp->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if (array_key_exists($name, $data)) {
                // assign as-is; casting/validation is up to concrete VM if needed
                $prop->setValue($vm, $data[$name]);
            }
        }

        return $vm;
    }
}
