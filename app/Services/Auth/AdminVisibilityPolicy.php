<?php
// v0.3.110
// AdminVisibilityPolicy — единая точка правил доступа к админке (категории/форумы/перки/абилити).
// Роль id >= 6 — доступ к админке; методы canManage* используют canAccessAdmin().
// FIX: подтверждена поддержка canManageAbilities(); выравнивание комментариев и формата.

namespace App\Services\Auth;

class AdminVisibilityPolicy
{
    /** Минимальная роль для админки: 6 = administrator */
    private const ADMIN_MIN_ROLE = 6;

    /** Базовый доступ в админку */
    public function canAccessAdmin(int $viewerRoleId): bool
    {
        return $viewerRoleId >= self::ADMIN_MIN_ROLE;
    }

    /** Управление категориями */
    public function canManageCategories(int $viewerRoleId): bool
    {
        return $this->canAccessAdmin($viewerRoleId);
    }

    /** Управление форумами */
    public function canManageForums(int $viewerRoleId): bool
    {
        return $this->canAccessAdmin($viewerRoleId);
    }

    /** Управление перками */
    public function canManagePerks(int $viewerRoleId): bool
    {
        return $this->canAccessAdmin($viewerRoleId);
    }

    /** Управление способностями (abilities) */
    public function canManageAbilities(int $viewerRoleId): bool
    {
        return $this->canAccessAdmin($viewerRoleId);
    }

    // --- Тонкие проверки на будущее (когда появятся флаги на записях) ---

    public function canEditCategory(int $viewerRoleId, array $category): bool
    {
        return $this->canManageCategories($viewerRoleId);
    }

    public function canEditForum(int $viewerRoleId, array $forum): bool
    {
        return $this->canManageForums($viewerRoleId);
    }
}
