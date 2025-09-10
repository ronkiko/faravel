<?php // v0.4.1
/* app/Http/ViewModels/Auth/RegisterPageVM.php
Purpose: VM страницы регистрации под строгий Blade.
FIX: Новый класс. Готовит только данные для полей и формы.
*/
namespace App\Http\ViewModels\Auth;

use App\Contracts\ViewModel\ArrayBuildable;

final class RegisterPageVM implements ArrayBuildable
{
    public string $title = 'Регистрация';
    public string $action = '/register';
    public string $csrf = '';
    public string $prefill_username = '';

    /**
     * @param array<string,mixed> $data
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): static
    {
        $vm = new static();
        $vm->title            = (string)($data['title'] ?? 'Регистрация');
        $vm->action           = (string)($data['action'] ?? '/register');
        $vm->csrf             = (string)($data['csrf']   ?? '');
        $vm->prefill_username = (string)($data['prefill_username'] ?? '');
        if ($vm->csrf === '' || $vm->action === '') {
            throw new \InvalidArgumentException('csrf/action must be non-empty');
        }
        return $vm;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'title'            => $this->title,
            'action'           => $this->action,
            'csrf'             => $this->csrf,
            'prefill_username' => $this->prefill_username,
        ];
    }
}
