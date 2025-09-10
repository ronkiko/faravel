<?php // v0.1.0
/* app/Services/Forum/ForumHomeService.php — v0.1.0
Назначение: сервис домашней страницы форума — находит первую видимую категорию.
FIX: начальная версия. SQL сортирует по: order_id IS NULL, order_id, title.
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class ForumHomeService
{
    public function firstVisibleCategorySlug(): ?string
    {
        $rows = DB::select(
            "SELECT slug
               FROM categories
              WHERE is_visible = 1
              ORDER BY (order_id IS NULL), order_id, title
              LIMIT 1"
        );
        $row = $rows[0] ?? null;
        if (!$row) return null;
        $slug = $row->slug ?? ($row['slug'] ?? null);
        return $slug !== null ? (string)$slug : null;
    }
}
