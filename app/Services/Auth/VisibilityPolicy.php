<?php // v0.3.106
// VisibilityPolicy — простые Policy-правила видимости/доступа для форума/категории в стиле Laravel.
// FIX: новая единая точка правил: canViewCategory(), canViewForum(), canCreateTopic().

namespace App\Services\Auth;

class VisibilityPolicy
{
    /** Категория видима пользователю c данным role_id? */
    public function canViewCategory(array $category, int $viewerRoleId): bool
    {
        return (int)($category['is_visible'] ?? 0) === 1
            && $viewerRoleId >= (int)($category['min_group'] ?? 0);
    }

    /** Форум видим пользователю c данным role_id? */
    public function canViewForum(array $forum, int $viewerRoleId): bool
    {
        return (int)($forum['is_visible'] ?? 0) === 1
            && $viewerRoleId >= (int)($forum['min_group'] ?? 0);
    }

    /** Может ли пользователь создавать тему в данном форуме? (виден, не залочен, авторизован) */
    public function canCreateTopic(array $forum, int $viewerRoleId, bool $isAuthenticated): bool
    {
        if (!$isAuthenticated) return false;
        if ((int)($forum['is_locked'] ?? 0) === 1) return false;
        return $this->canViewForum($forum, $viewerRoleId);
    }
}
