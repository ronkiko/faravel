<?php
/* app/Policies/Forum/TopicPolicy.php v0.2.1
Назначение: предикаты доступа к теме (reply и др.).
FIX: перенесён из Controllers в Policies, обновлён namespace. */
namespace App\Policies\Forum;

use App\Services\Auth\AbilityService;

final class TopicPolicy {
    public function canReply(?array $user, $topic): bool {
        $topicId = is_array($topic) ? (string)($topic['id'] ?? '')
                 : (is_object($topic) ? (string)($topic->id ?? '') : '');
        return $topicId !== '' && $user !== null
            && AbilityService::has($user, 'forum.post.create', $topic);
    }
}
