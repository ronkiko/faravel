<?php // v0.4.2
/* app/Support/Format/TimeFormatter.php
Purpose: Универсальный форматтер «n назад» для слоя представления, без зависимостей.
FIX: Новый общий хелпер. Используется в HubPageVM и ShowTopicAction.
*/

namespace App\Support\Format;

final class TimeFormatter
{
    /**
     * Compact Russian "time ago" formatter.
     *
     * @param int|null $ts  Event unix timestamp. Null or <=0 → '—'.
     * @param int      $now Current unix timestamp (>=0).
     * @pre $now >= 0.
     * @return string Human-readable elapsed time in RU.
     * @example TimeFormatter::humanize(time()-90, time()) === '1 мин. назад'
     */
    public static function humanize(?int $ts, int $now): string
    {
        if ($ts === null || $ts <= 0) {
            return '—';
        }
        $d = max(0, $now - $ts);
        if ($d < 60) {
            return $d . ' с. назад';
        }
        $m = (int) floor($d / 60);
        if ($m < 60) {
            return $m . ' мин. назад';
        }
        $h = (int) floor($m / 60);
        if ($h < 24) {
            return $h . ' ч. назад';
        }
        $days = (int) floor($h / 24);
        if ($days < 30) {
            return $days . ' дн. назад';
        }
        $mon = (int) floor($days / 30);
        if ($mon < 12) {
            return $mon . ' мес. назад';
        }
        $y = (int) floor($mon / 12);
        return $y . ' г. назад';
    }
}
