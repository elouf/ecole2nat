<?php

namespace Ecole2Nat\Coach;

if (!defined('ABSPATH')) {
    exit;
}

class CoachAccessService
{
    private CoachAccessRepository $repository;

    public function __construct()
    {
        $this->repository = new CoachAccessRepository();
    }

    public function canView(): bool
    {
        return current_user_can('manage_options') || current_user_can('e2n_coach_access');
    }

    public function canPrepareGroup(int $groupId, string $sessionDate): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        if (!$this->canView()) {
            return false;
        }

        $userId = get_current_user_id();
        if ($this->repository->isTitular($userId, $groupId)) {
            return true;
        }

        return $sessionDate >= current_time('Y-m-d')
            && $this->repository->isSubstitute($userId, $groupId, $sessionDate);
    }

    public function canOperateGroup(int $groupId, string $sessionDate): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        if (!$this->canView()) {
            return false;
        }

        $userId = get_current_user_id();
        if ($this->repository->isTitular($userId, $groupId)) {
            return true;
        }

        return $sessionDate === current_time('Y-m-d')
            && $this->repository->isSubstitute($userId, $groupId, $sessionDate);
    }

    public function accessLabel(int $groupId, string $sessionDate): string
    {
        if (current_user_can('manage_options')) {
            return __('Administrateur · édition autorisée', 'ecole2nat');
        }

        if ($this->repository->isTitular(get_current_user_id(), $groupId)) {
            return __('Titulaire · édition autorisée', 'ecole2nat');
        }

        if ($sessionDate === current_time('Y-m-d')
            && $this->repository->isSubstitute(get_current_user_id(), $groupId, $sessionDate)) {
            return __('Remplaçant · édition autorisée aujourd’hui', 'ecole2nat');
        }

        if ($sessionDate > current_time('Y-m-d')
            && $this->repository->isSubstitute(get_current_user_id(), $groupId, $sessionDate)) {
            return __('Remplaçant prévu · préparation autorisée', 'ecole2nat');
        }

        return __('Consultation', 'ecole2nat');
    }

    public function isSubstituteForDate(int $groupId, string $date): bool
    {
        return $this->repository->isSubstitute(get_current_user_id(), $groupId, $date);
    }

    public function titularGroupIds(?int $userId = null): array
    {
        return $this->repository->titularGroupIds($userId ?? get_current_user_id());
    }

    public function saveAssignments(int $userId, array $groupIds): bool
    {
        return $this->repository->replaceAssignments($userId, $groupIds);
    }

    public function addSubstitution(int $userId, int $groupId, string $date, int $createdBy): bool
    {
        return $this->repository->addSubstitution($userId, $groupId, $date, $createdBy);
    }

    public function deleteSubstitution(int $id): bool
    {
        return $this->repository->deleteSubstitution($id);
    }

    public function substitutions(string $fromDate): array
    {
        return $this->repository->substitutions($fromDate);
    }

    public function clearUserAccess(int $userId): bool
    {
        return $this->repository->clearUserAccess($userId);
    }
}
