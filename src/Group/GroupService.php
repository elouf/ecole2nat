<?php

namespace Ecole2Nat\Group;

if (!defined('ABSPATH')) {
    exit;
}

class GroupService
{
    private GroupRepository $repository;

    public function __construct()
    {
        $this->repository = new GroupRepository();
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function create(
        int $seasonId,
        int $categoryId,
        string $name,
        string $color,
        ?int $weekday,
        ?string $startTime,
        ?string $endTime
    ): array {
        if ($this->repository->exists($seasonId, $name)) {
            return [
                'success' => false,
                'message' => 'duplicate',
            ];
        }

        $created = $this->repository->create(
            $seasonId,
            $categoryId,
            $name,
            $color,
            $weekday,
            $startTime,
            $endTime
        );

        return [
            'success' => $created,
            'message' => $created ? 'created' : 'error',
        ];
    }

    public function toggleActive(int $id): bool
    {
        return $this->repository->toggleActive($id);
    }

    public function active(): array
    {
        return $this->repository->active();
    }
}