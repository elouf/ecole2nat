<?php

namespace Ecole2Nat\Coach;

if (!defined('ABSPATH')) {
    exit;
}

class CoachAccessService
{
    private CoachAccessRepository $repository;

    public function __construct(?CoachAccessRepository $repository = null)
    {
        $this->repository = $repository ?? new CoachAccessRepository();
    }

    public function canView(): bool
    {
        return current_user_can('manage_options') || current_user_can('e2n_coach_access');
    }

    public function canEvaluateGroup(int $groupId): bool
    {
        return $this->canView();
    }

    public function titularGroupIds(?int $userId = null): array
    {
        return $this->repository->titularGroupIds($userId ?? get_current_user_id());
    }

    public function saveAssignments(int $userId, array $groupIds): bool
    {
        return $this->repository->replaceAssignments($userId, $groupIds);
    }

    public function clearUserAccess(int $userId): bool
    {
        return $this->repository->clearUserAccess($userId);
    }
}
