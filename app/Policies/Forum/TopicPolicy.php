<?php // v0.4.1
/* app/Policies/Forum/TopicPolicy.php
Purpose: Реализация политики доступа к теме форума. Совместима с DI через
TopicPolicyContract и делегирует проверку в AbilityService.
FIX: Приведён namespace/версию к базовой 0.4.x, реализован новый контракт,
     метод снабжён строгим PHPDoc.
*/
namespace App\Policies\Forum;

use App\Contracts\Policies\Forum\TopicPolicyContract;
use App\Services\Auth\AbilityService;

final class TopicPolicy implements TopicPolicyContract
{
    /**
     * {@inheritdoc}
     *
     * @param array|null   $user
     * @param array|object $topic
     *
     * @return bool
     */
    public function canReply(?array $user, array|object $topic): bool
    {
        // Accept array or object; extract topic id safely.
        $topicId = null;

        if (is_array($topic)) {
            $topicId = isset($topic['id']) ? (string)$topic['id'] : null;
        } else {
            foreach (['id', 'topic_id'] as $p) {
                if (isset($topic->$p)) {
                    $topicId = (string)$topic->$p;
                    break;
                }
            }
        }

        if ($topicId === null || $topicId === '') {
            return false;
        }

        // Delegate to ability checker. No side effects here.
        return AbilityService::has($user, 'forum.post.create', $topic);
    }
}
