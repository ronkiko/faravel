<?php
/* tests/Forum/Unit/TopicPolicyTest.php — v0.1.1
Назначение: smoke-тест TopicPolicy::canReply — метод существует и возвращает bool.
FIX: вызов canReply(...) вместо reply(...). */

use App\Policies\Forum\TopicPolicy;

function test__TopicPolicy_canReply_smoke(): void {
    if (!class_exists(TopicPolicy::class)) {
        throw new RuntimeException('SKIP: TopicPolicy absent');
    }
    $pol = new TopicPolicy();
    $user  = ['id'=>'U','role_id'=>1,'group_id'=>1];
    $topic = ['id'=>'T','category_id'=>'C'];
    $ok = $pol->canReply($user, $topic);
    assert_true(is_bool($ok), 'canReply() must return bool');
}
