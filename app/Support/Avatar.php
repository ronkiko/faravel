<?php // v0.3.1
namespace App\Support;

final class Avatar
{
    /** Вернёт URL аватарки: явный src → /avatars/<id>.png → дефолт */
    public static function urlFor($userOrId, ?string $override = null): string
    {
        if ($override !== null && $override !== '') return $override;
        $id = is_array($userOrId) ? (string)($userOrId['id'] ?? '') : (string)$userOrId;
        return $id !== '' ? '/avatars/'.$id.'.png' : '/style/avatar-default.png';
    }

    /** Сгенерирует <img> с нужной формой (circle|square|star) и размером */
    public static function tag($userOrId, array $opts = []): string
    {
        $size  = max(16, min(512, (int)($opts['size'] ?? 72)));
        $shape = in_array(($opts['shape'] ?? 'circle'), ['circle','square','star'], true)
               ? $opts['shape'] : 'circle';

        $alt   = htmlspecialchars((string)($opts['alt'] ?? 'avatar'), ENT_QUOTES, 'UTF-8');
        $extra = htmlspecialchars(trim((string)($opts['class'] ?? '')), ENT_QUOTES, 'UTF-8');

        $src = htmlspecialchars(self::urlFor($userOrId, $opts['src'] ?? null), ENT_QUOTES, 'UTF-8');
        $fallback = '/style/avatar-default.png';

        $class = trim("avatar avatar--{$shape} {$extra}");
        $style = 'width:'.$size.'px;height:'.$size.'px;'.trim((string)($opts['style'] ?? ''));

        return '<img src="'.$src
            .'" alt="'.$alt
            .'" class="'.$class
            .'" style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'"'
            .' onerror="this.onerror=null;this.src=\''.$fallback.'\';">'
        ;
    }
}
