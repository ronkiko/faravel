<?php
/* tests/Forum/Service/HubQueryServiceTest.php v0.1.0
Назначение: сервис-тесты HubQueryService на тестовых данных из дампа.
FIX: первый релиз. */

use App\Services\Forum\HubQueryService;

function test__HubQueryService_find_and_list(): void {
    $svc = new HubQueryService();
    $tag = $svc->findTagBySlugOrId('linux');
    assert_true(is_array($tag) && ($tag['id'] ?? '') !== '', 'linux tag not found');
    $q = $svc->listTopicsByTag((string)$tag['id'], 1, 20, 'last');
    assert_true(isset($q['items']) && is_array($q['items']));
    assert_true(isset($q['total']));
}
