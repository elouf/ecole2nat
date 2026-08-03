<?php

namespace Ecole2Nat\Session;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SessionExerciseRepository
{
    public function allByPart(int $partId): array
    {
        global $wpdb;

        $sessionExercisesTable = Config::table('session_exercises');
        $exercisesTable = Config::table('exercises');

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    session_exercises.*,
                    exercises.name,
                    exercises.description,
                    exercises.duration AS default_duration
                FROM {$sessionExercisesTable} AS session_exercises
                INNER JOIN {$exercisesTable} AS exercises
                    ON exercises.id = session_exercises.exercise_id
                WHERE session_exercises.part_id = %d
                ORDER BY
                    session_exercises.position ASC,
                    session_exercises.id ASC",
                $partId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $partId,
        int $exerciseId,
        ?int $customDuration = null,
        string $coachNotes = ''
    ): bool {
        global $wpdb;

        $position = $this->nextPosition($partId);

        $result = $wpdb->insert(
            Config::table('session_exercises'),
            [
                'part_id'         => $partId,
                'exercise_id'     => $exerciseId,
                'position'        => $position,
                'custom_duration' => $customDuration,
                'coach_notes'     => $coachNotes,
                'created_at'      => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        return $result !== false;
    }

    public function exists(int $partId, int $exerciseId): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM ' . Config::table('session_exercises') . '
                WHERE part_id = %d
                AND exercise_id = %d',
                $partId,
                $exerciseId
            )
        );

        return $count > 0;
    }

    private function nextPosition(int $partId): int
    {
        global $wpdb;

        $maximum = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT MAX(position)
                FROM ' . Config::table('session_exercises') . '
                WHERE part_id = %d',
                $partId
            )
        );

        return $maximum === null
            ? 1
            : (int) $maximum + 1;
    }
}