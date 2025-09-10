<?php // v0.4.1
/* app/Http/ViewModels/Auth/LoginPageVM.php
Purpose: VM страницы логина под строгий Blade: только данные для рендера формы.
FIX: Новый класс. Никакой логики: всё вычислено в контроллере.
*/
namespace App\Http\ViewModels\Auth;

use App\Contracts\ViewModel\ArrayBuildable;

/**
 * LoginPageVM — плоские данные для формы логина.
 */
final class LoginPageVM implements ArrayBuildable
{
    /** @var string */
    public string $title = 'Вход';
    /** @var string */
    public string $action = '/login';
    /** @var string */
    public string $csrf = '';
    /** @var string */
    public string $prefill_username = '';

    /**
     * Factory from named array.
     *
     * Preconditions: action, csrf — непустые строки.
     * Side effects: нет.
     *
     * @param array<string,mixed> $data
     * @return static
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): static
    {
        $vm = new static();
        $vm->title            = (string)($data['title'] ?? 'Вход');
        $vm->action           = (string)($data['action'] ?? '/login');
        $vm->csrf             = (string)($data['csrf']   ?? '');
        $vm->prefill_username = (string)($data['prefill_username'] ?? '');
        if ($vm->csrf === '' || $vm->action === '') {
            throw new \InvalidArgumentException('csrf/action must be non-empty');
        }
        return $vm;
    }

    /**
     * Представление VM для Blade.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'title'            => $this->title,
            'action'           => $this->action,
            'csrf'             => $this->csrf,
            'prefill_username' => $this->prefill_username,
            // Удобные флаги для autofocus (шаблон не думает)
            'focus_user'       => $this->prefill_username === '',
            'focus_pass'       => $this->prefill_username !== '',
        ];
    }
}
