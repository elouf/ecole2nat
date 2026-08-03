<?php

namespace Ecole2Nat\Session;

if (!defined('ABSPATH')) {
    exit;
}

class SessionExerciseService
{
    private SessionExerciseRepository $repository;

    public function __construct()
    {
        $this->repository = new SessionExerciseRepository();
    }

    public function allByPart(int $partId): array
    {
        return $this->repository->allByPart($partId);
    }

    public function create(
        int $partId,
        int $exerciseId,
        ?int $customDuration = null,
        string $coachNotes = ''
    ): array {
        if ($partId <= 0 || $exerciseId <= 0) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        if ($this->repository->exists($partId, $exerciseId)) {
            return [
                'success' => false,
                'message' => 'duplicate',
            ];
        }

        $created = $this->repository->create(
            $partId,
            $exerciseId,
            $customDuration,
            $coachNotes
        );

        return [
            'success' => $created,
            'message' => $created ? 'created' : 'error',
        ];
    }
}