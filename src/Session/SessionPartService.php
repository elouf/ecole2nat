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

    public function create(
        int $sessionId,
        string $title
    ): array {
        $title = trim($title);

        if ($title === '') {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        if ($this->repository->exists($sessionId, $title)) {
            return [
                'success' => false,
                'message' => 'duplicate',
            ];
        }

        $parts = $this->repository->allBySession($sessionId);

        $position = count($parts) + 1;

        $created = $this->repository->create(
            $sessionId,
            $title,
            $position
        );

        return [
            'success' => $created,
            'message' => $created ? 'created' : 'error',
        ];
    }
}