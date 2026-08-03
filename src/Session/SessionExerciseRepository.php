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
                    exercises.objectives,
                    exercises.equipment,
                    exercises.difficulty
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
        ?int $duration = null,
        string $coachNotes = ''
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('session_exercises'),
            [
                'part_id'     => $partId,
                'exercise_id' => $exerciseId,
                'position'    => $this->nextPosition($partId),
                'duration'    => $duration,
                'coach_notes' => $coachNotes,
                'created_at'  => current_time('mysql'),
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

    public function update(
        int $id,
        int $duration,
        string $coachNotes = ''
    ): bool {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('session_exercises'),
            [
                'duration'    => $duration,
                'coach_notes' => $coachNotes,
                'updated_at'  => current_time('mysql'),
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            Config::table('session_exercises'),
            [
                'id' => $id,
            ],
            [
                '%d',
            ]
        );

        return $result !== false;
    }

    public function move(int $id, string $direction): bool
    {
        global $wpdb;

        $table = Config::table('session_exercises');

        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, part_id, position
                FROM {$table}
                WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($current)) {
            return false;
        }

        $operator = $direction === 'up' ? '<' : '>';
        $order = $direction === 'up' ? 'DESC' : 'ASC';

        $neighbour = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, position
                FROM {$table}
                WHERE part_id = %d
                AND position {$operator} %d
                ORDER BY position {$order}, id {$order}
                LIMIT 1",
                (int) $current['part_id'],
                (int) $current['position']
            ),
            ARRAY_A
        );

        if (!is_array($neighbour)) {
            return true;
        }

        $currentUpdated = $wpdb->update(
            $table,
            [
                'position'   => (int) $neighbour['position'],
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => (int) $current['id'],
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($currentUpdated === false) {
            return false;
        }

        $neighbourUpdated = $wpdb->update(
            $table,
            [
                'position'   => (int) $current['position'],
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => (int) $neighbour['id'],
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $neighbourUpdated !== false;
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