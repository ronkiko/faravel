<?php // v0.4.1
/* app/Http/ViewModels/Layout/FlashVM.php
Purpose: VM флеш-сообщений под строгий Blade.
FIX: fromSession() теперь читает и flash-значения через Session::old('success'|'error'),
     а затем объединяет их с постоянными ключами success|error и *_list. Это устраняет
     пропажу плашек после редиректов из троттлинга и других действий.
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
     * Создать VM из массива.
     *
     * @param array{success?:mixed,error?:mixed} $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->success = self::norm($data['success'] ?? []);
        $self->error   = self::norm($data['error'] ?? []);
        return $self;
    }

    /**
     * Считать флеши из сессии.
     *
     * Правила:
     * - В первую очередь берём flash-ключи через old('success'|'error').
     * - Затем добавляем постоянные success|error или success_list|error_list.
     * - Пустые строки отбрасываются, дубликаты убираются, порядок сохраняется.
     *
     * @param Session $s
     * @return self
     */
    public static function fromSession(Session $s): self
    {
        // flash (текущий запрос)
        $succFlash = self::norm($s->old('success', []));
        $errFlash  = self::norm($s->old('error', []));

        // persistent fallbacks
        $succList  = self::norm($s->get('success_list', []));
        $errList   = self::norm($s->get('error_list', []));
        $succOne   = self::norm($s->get('success', []));
        $errOne    = self::norm($s->get('error', []));

        // merge with de-duplication
        $success = self::unique(array_merge($succFlash, $succList, $succOne));
        $error   = self::unique(array_merge($errFlash,  $errList,  $errOne));

        return self::fromArray(['success' => $success, 'error' => $error]);
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

    /** @param array<int,string> $arr @return array<int,string> */
    private static function unique(array $arr): array
    {
        $seen = [];
        $out  = [];
        foreach ($arr as $s) {
            if (!isset($seen[$s])) {
                $seen[$s] = true;
                $out[] = $s;
            }
        }
        return $out;
    }
}
