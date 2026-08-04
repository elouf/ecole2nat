<?php

namespace Ecole2Nat\Session;

if (!defined('ABSPATH')) {
    exit;
}

class SessionPartService
{
    private SessionPartRepository $repository;

    public function __construct()
    {
        $this->repository = new SessionPartRepository();
    }

    public function allBySession(int $sessionId): array
    {
        return $this->repository->allBySession($sessionId);
    }

    public function create(int $sessionId, string $title): array
    {
        $title = trim($title);

        if ($sessionId <= 0 || $title === '') {
            return ['success' => false, 'message' => 'invalid'];
        }

        if ($this->repository->exists($sessionId, $title)) {
            return ['success' => false, 'message' => 'part_duplicate'];
        }

        $created = $this->repository->create($sessionId, $title);

        return [
            'success' => $created,
            'message' => $created ? 'part_created' : 'error',
        ];
    }

    public function update(int $id, int $sessionId, string $title): array
    {
        $title = trim($title);

        if ($id <= 0 || $sessionId <= 0 || $title === '') {
            return ['success' => false, 'message' => 'invalid'];
        }

        if ($this->repository->exists($sessionId, $title, $id)) {
            return ['success' => false, 'message' => 'part_duplicate'];
        }

        $updated = $this->repository->update($id, $title);

        return [
            'success' => $updated,
            'message' => $updated ? 'part_updated' : 'error',
        ];
    }

    public function delete(int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'invalid'];
        }

        $deleted = $this->repository->delete($id);

        return [
            'success' => $deleted,
            'message' => $deleted ? 'part_deleted' : 'error',
        ];
    }

    public function move(int $id, string $direction): array
    {
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            return ['success' => false, 'message' => 'invalid'];
        }

        $moved = $this->repository->move($id, $direction);

        return [
            'success' => $moved,
            'message' => $moved ? 'part_moved' : 'error',
        ];
    }
}
