<?php
/* tests/Forum/Service/CategoryQueryServiceTest.php v0.1.0
Назначение: сервис-тесты CategoryQueryService на тестовых данных из дампа.
FIX: первый релиз. */

use App\Services\Forum\CategoryQueryService;

function test__CategoryQueryService_find_and_toptags(): void {
    $svc = new CategoryQueryService();
    $cat = $svc->findCategoryBySlugOrId('test');
    assert_true(is_array($cat) && ($cat['id'] ?? '') !== '', 'category test not found');
    $tags = $svc->topTagsForCategory((string)$cat['id'], 10);
    assert_true(is_array($tags));
}
