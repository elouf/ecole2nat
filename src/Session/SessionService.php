<?php

namespace Ecole2Nat\Session;

if (!defined('ABSPATH')) {
    exit;
}

class SessionService
{
    private SessionRepository $repository;

    public function __construct()
    {
        $this->repository = new SessionRepository();
    }

    public function all(): array
    {
        return $this->repository->all();
    }

    public function create(
        int $categoryId,
        string $name,
        string $objectives = ''
    ): array {
        if ($this->repository->exists($categoryId, $name)) {
            return [
                'success' => false,
                'message' => 'duplicate',
            ];
        }

        $created = $this->repository->create(
            $categoryId,
            $name,
            $objectives
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

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function update(
        int $id,
        int $categoryId,
        string $name,
        string $objectives = ''
    ): array {
        if ($id <= 0 || $categoryId <= 0 || trim($name) === '') {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        $updated = $this->repository->update(
            $id,
            $categoryId,
            $name,
            $objectives
        );

        return [
            'success' => $updated,
            'message' => $updated ? 'session_updated' : 'error',
        ];
    }
}