<?php

namespace App\Support;

final class Slugger
{
    public const DEFAULT_SEPARATOR = '-';
    public const DEFAULT_MAX_LEN   = 100;

    /**
     * Сделать слаг из строки с жёсткими фоллбэками под кириллицу.
     */
    public static function make(string $value, string $sep = self::DEFAULT_SEPARATOR, int $maxLen = self::DEFAULT_MAX_LEN): string
    {
        $v = trim($value);
        $v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);

        // 1) Пытаемся ICU transliterator (если есть)
        if (class_exists(\Transliterator::class)) {
            $rules = 'Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC';
            $tr = \Transliterator::create($rules);
            if ($tr) {
                $tmp = $tr->transliterate($v);
                if (is_string($tmp) && $tmp !== '') {
                    $v = $tmp;
                }
            }
        }

        // 2) Всегда прогоняем кириллическую таблицу (не навредит ASCII)
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
            'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
            'х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'ї'=>'yi','і'=>'i','є'=>'ye','ґ'=>'g',
        ];
        $v = strtr($v, $map);

        // 3) На всякий случай — iconv в ASCII (с отпилом диакритики)
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
            if (is_string($tmp) && $tmp !== '') {
                $v = $tmp;
            }
        }

        // 4) Разрешаем только [a-z0-9] + разделитель
        $v = preg_replace('/[^a-z0-9]+/i', $sep, (string)$v);

        // 5) Схлопываем разделители и чистим края
        $quoted = preg_quote($sep, '/');
        $v = preg_replace('/' . $quoted . '+/', $sep, (string)$v);
        $v = trim((string)$v, $sep . ' _.');

        // 6) Ограничение длины
        if ($maxLen > 0 && strlen($v) > $maxLen) {
            $v = substr($v, 0, $maxLen);
            $v = rtrim($v, $sep);
        }

        return ($v === '') ? 'n-a' : $v;
    }

    /**
     * @deprecated Используйте уникальность через UNIQUE индекс + retry на вставке/обновлении.
     * Этот метод оставлен только для обратной совместимости с хелпером unique_slug().
     * Он НИЧЕГО не проверяет в БД и просто возвращает базовый слаг.
     */
    public static function unique(
        string $base,
        string $table,
        string $column = 'slug',
        string $idColumn = 'id',
        ?string $excludeId = null,
        string $sep = self::DEFAULT_SEPARATOR,
        int $maxLen = self::DEFAULT_MAX_LEN
    ): string {
        // Намеренно не делаем SELECT’ов: уникальность обеспечивается на уровне БД,
        // а прикладной код должен выполнять retry при конфликте (см. AdminCategoryController::store/update).
        return self::make($base, $sep, $maxLen);
    }
}
