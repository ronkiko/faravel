<?php // v0.4.1
/* app/Contracts/ViewModel/ViewModelContract.php
Purpose: Base contract for all ViewModels (VM) in Faravel. Guarantees uniform toArray() export
         that Blade can consume safely (no DB/side-effects).
FIX: Introduced the interface to standardize VM export across the project.
*/
namespace App\Contracts\ViewModel;

interface ViewModelContract
{
    /**
     * Export prepared data for Blade view. No I/O or DB access is allowed here.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array;
}
