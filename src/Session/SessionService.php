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

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(
        int $categoryId,
        string $name,
        string $objectives = ''
    ): array {
        $name = trim($name);

        if ($categoryId <= 0 || $name === '') {
            return ['success' => false, 'message' => 'invalid', 'id' => 0];
        }

        if ($this->repository->exists($categoryId, $name)) {
            return ['success' => false, 'message' => 'duplicate', 'id' => 0];
        }

        $id = $this->repository->create($categoryId, $name, $objectives);

        return [
            'success' => $id > 0,
            'message' => $id > 0 ? 'session_created' : 'error',
            'id' => $id,
        ];
    }

    public function update(
        int $id,
        int $categoryId,
        string $name,
        string $objectives = ''
    ): array {
        $name = trim($name);

        if ($id <= 0 || $categoryId <= 0 || $name === '') {
            return ['success' => false, 'message' => 'invalid'];
        }

        if ($this->repository->exists($categoryId, $name, $id)) {
            return ['success' => false, 'message' => 'duplicate'];
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

    public function toggleActive(int $id): bool
    {
        return $this->repository->toggleActive($id);
    }

    public function duplicate(int $id): array
    {
        $session = $this->repository->find($id);
        if ($session === null) {
            return ['success' => false, 'message' => 'error', 'id' => 0];
        }

        $baseName = sprintf(__('Copie de %s', 'ecole2nat'), $session['name']);
        $name = $baseName;
        $suffix = 2;

        while ($this->repository->exists((int) $session['category_id'], $name)) {
            $name = sprintf('%s (%d)', $baseName, $suffix);
            $suffix++;
        }

        $newId = $this->repository->duplicate($id, $name);

        return [
            'success' => $newId > 0,
            'message' => $newId > 0 ? 'duplicated' : 'error',
            'id' => $newId,
        ];
    }
}
