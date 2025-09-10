<?php // v0.1.0
/* app/Http/ViewModels/Layout/FlashVM.php — v0.1.0
Назначение: VM флеш-сообщений под строгий Blade.
FIX: начальная версия; нормализуем в массивы строк.
*/
namespace App\Http\ViewModels\Layout;

final class FlashVM
{
    /** @param array{success?:string|array<int,string>,error?:string|array<int,string>} $flash */
    public static function from(array $flash): array
    {
        $norm = static function ($v): array {
            if ($v === null || $v === '') return [];
            if (is_string($v)) return [$v];
            if (is_array($v))  return array_values(array_filter(array_map('strval', $v), fn($s)=>$s!==''));
            return [];
        };

        return [
            'success' => $norm($flash['success'] ?? []),
            'error'   => $norm($flash['error'] ?? []),
        ];
    }
}
