<?php // v0.2.0
/* app/Http/ViewModels/Layout/FlashVM.php
Назначение: VM флеш-сообщений под строгий Blade.
FIX: Вместо массива — полноценная VM: fromArray()/fromSession() + toArray().
     Нормализация в массивы строк; центр. сборка из сессии.
*/
namespace App\Http\ViewModels\Layout;

use Faravel\Http\Session;

final class FlashVM
{
    /** @var string[] */
    public array $success = [];
    /** @var string[] */
    public array $error   = [];

    /**
     * @param array{success?:string|string[], error?:string|string[]} $src
     */
    public static function fromArray(array $src): self
    {
        $self = new self();
        $self->success = self::norm($src['success'] ?? []);
        $self->error   = self::norm($src['error']   ?? []);
        return $self;
    }

    /**
     * Сборка из сессии (канонично для контроллеров).
     */
    public static function fromSession(Session $s): self
    {
        $succ = $s->get('success_list');
        if (!$succ) {
            $v = $s->get('success');
            $succ = $v ? [(string)$v] : [];
        }

        $err = $s->get('error_list');
        if (!$err) {
            $v = $s->get('error');
            $err = $v ? [(string)$v] : [];
        }

        return self::fromArray(['success' => $succ, 'error' => $err]);
    }

    /** @return array{success:string[], error:string[]} */
    public function toArray(): array
    {
        return ['success' => $this->success, 'error' => $this->error];
    }

    /** @param mixed $v @return string[] */
    private static function norm($v): array
    {
        if ($v === null || $v === '') return [];
        if (is_string($v)) return [$v];
        if (is_array($v)) {
            $out = [];
            foreach ($v as $s) {
                $s = (string)$s;
                if ($s !== '') $out[] = $s;
            }
            return $out;
        }
        return [];
    }
}
