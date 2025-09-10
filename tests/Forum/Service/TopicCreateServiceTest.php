<?php
/* tests/Forum/Service/TopicCreateServiceTest.php v0.1.0
Назначение: сервис-тест создания темы и первого поста; по умолчанию SKIP чтобы
не мутировать БД. Установите ENV TEST_WRITE_DB=1 для запуска.
FIX: первый релиз с безопасной защитой. */

use App\Services\Forum\TopicCreateService;

function test__TopicCreateService_createFromHub(): void {
    if ((string)getenv('TEST_WRITE_DB') !== '1') {
        throw new RuntimeException('SKIP: write DB disabled (export TEST_WRITE_DB=1 to enable)');
    }
    $svc = new TopicCreateService();
    // Требуется действующий user_id в БД. В тестовом дампе есть админ:
    $userId = '1bd5d75c-655c-427f-a424-339c8840f799';
    $res = $svc->createFromHub($userId, 'linux', 'Тестовая тема '.bin2hex(random_bytes(2)), 'hello');
    assert_true(isset($res['postId']) && $res['postId'] !== '');
}
