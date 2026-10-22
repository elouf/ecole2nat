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
        return $this->canCoach() || $this->canManageCompetitions();
    }

    public function canEvaluateGroup(int $groupId): bool
    {
        return $this->canCoach();
    }

    public function canCoach(): bool
    {
        return current_user_can('manage_options') || current_user_can('e2n_coach_access');
    }

    public function canManageCompetitions(): bool
    {
        return $this->canCoach() || current_user_can('e2n_competition_manager_access');
    }

    public function canManageCompetitionRegistrations(): bool
    {
        return $this->canCoach();
    }

    public function isCompetitionManagerOnly(): bool
    {
        return !$this->canCoach() && current_user_can('e2n_competition_manager_access');
    }

    public function canManageDistributions(): bool
    {
        return $this->canCoach();
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
