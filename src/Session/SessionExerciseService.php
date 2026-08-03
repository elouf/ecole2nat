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
        ?int $duration = null,
        string $coachNotes = ''
    ): array {
        if (
            $partId <= 0
            || $exerciseId <= 0
            || $duration === null
            || $duration <= 0
        ) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        if ($this->repository->exists($partId, $exerciseId)) {
            return [
                'success' => false,
                'message' => 'exercise_duplicate',
            ];
        }

        $created = $this->repository->create(
            $partId,
            $exerciseId,
            $duration,
            trim($coachNotes)
        );

        return [
            'success' => $created,
            'message' => $created ? 'exercise_created' : 'error',
        ];
    }

    public function update(
        int $id,
        int $duration,
        string $coachNotes = ''
    ): array {
        if ($id <= 0 || $duration <= 0) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        $updated = $this->repository->update(
            $id,
            $duration,
            trim($coachNotes)
        );

        return [
            'success' => $updated,
            'message' => $updated ? 'exercise_updated' : 'error',
        ];
    }

    public function delete(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        $deleted = $this->repository->delete($id);

        return [
            'success' => $deleted,
            'message' => $deleted ? 'exercise_deleted' : 'error',
        ];
    }

    public function move(int $id, string $direction): array
    {
        if (
            $id <= 0
            || !in_array($direction, ['up', 'down'], true)
        ) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        $moved = $this->repository->move($id, $direction);

        return [
            'success' => $moved,
            'message' => $moved ? 'exercise_moved' : 'error',
        ];
    }
}