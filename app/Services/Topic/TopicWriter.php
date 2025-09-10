<?php // v0.3.103 — TopicWriter: транзакционное создание темы + первого поста.

namespace App\Services\Topic;

use App\Services\Support\IdGenerator;
use Faravel\Support\Facades\DB;

class TopicWriter
{
    public function __construct(private IdGenerator $ids) {}

    /**
     * @return string topicId
     * @throws \Throwable
     */
    public function createTopic(string $userId, string $categoryId, ?string $forumId, string $title, string $content): string
    {
        $now     = time();
        $topicId = $this->ids->uuidv4();
        $postId  = $this->ids->uuidv4();

        DB::beginTransaction();
        try {
            DB::insert(
                "INSERT INTO topics (id, category_id, forum_id, user_id, title, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$topicId, $categoryId, $forumId, $userId, $title, $now, $now]
            );

            DB::insert(
                "INSERT INTO posts (id, topic_id, user_id, content, created_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$postId, $topicId, $userId, $content, $now]
            );

            DB::commit();
            return $topicId;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
