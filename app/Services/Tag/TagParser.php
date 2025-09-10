<?php // v0.3.11
/*
app/Services/Tag/TagParser.php v0.3.11
Назначение: извлекает #теги из текста, нормализует в slug через Support\Slugger::make()
и возвращает уникальный список слегов. Поддержаны кириллица/латиница, многословные теги.
FIX: заменён вызов slug(...) на Slugger::make(...); убран DI экземпляра; фильтруется 'n-a'.
*/

namespace App\Services\Tag;

use App\Services\Support\Slugger;

class TagParser
{
    /** @return string[] */
    public function extractSlugs(string $text): array
    {
        // Разрешаем буквы/цифры/дефисы/пробелы после # до разделителя.
        preg_match_all('~#([a-zA-Z0-9\p{Cyrillic}\-\s]{1,80})~u', $text, $m);
        $unique = [];

        foreach ($m[1] ?? [] as $raw) {
            $slug = Slugger::make((string)$raw, '-', 64);
            // Отбрасываем пустые/служебные значения
            if ($slug !== '' && $slug !== 'n-a') {
                $unique[$slug] = true;
            }
        }

        return array_keys($unique);
    }
}
