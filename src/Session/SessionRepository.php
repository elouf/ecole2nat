<?php

namespace Ecole2Nat\Session;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SessionRepository
{
    public function all(): array
    {
        global $wpdb;

        $sessionsTable = Config::table('sessions');
        $categoriesTable = Config::table('categories');
        $partsTable = Config::table('session_parts');
        $sessionExercisesTable = Config::table('session_exercises');

        $results = $wpdb->get_results(
            "SELECT
                sessions.*,
                categories.name AS category_name,
                (
                    SELECT COUNT(*)
                    FROM {$partsTable} AS counted_parts
                    WHERE counted_parts.session_id = sessions.id
                ) AS parts_count,
                (
                    SELECT COALESCE(SUM(counted_exercises.duration), 0)
                    FROM {$partsTable} AS duration_parts
                    INNER JOIN {$sessionExercisesTable} AS counted_exercises
                        ON counted_exercises.part_id = duration_parts.id
                    WHERE duration_parts.session_id = sessions.id
                ) AS total_duration
            FROM {$sessionsTable} AS sessions
            INNER JOIN {$categoriesTable} AS categories
                ON categories.id = sessions.category_id
            WHERE sessions.is_library = 1
            ORDER BY
                categories.sort_order ASC,
                sessions.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $sessionsTable = Config::table('sessions');
        $categoriesTable = Config::table('categories');

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    sessions.*,
                    categories.name AS category_name
                FROM {$sessionsTable} AS sessions
                INNER JOIN {$categoriesTable} AS categories
                    ON categories.id = sessions.category_id
                WHERE sessions.id = %d
                LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function create(
        int $categoryId,
        string $name,
        string $objectives = ''
    ): int {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('sessions'),
            [
                'category_id' => $categoryId,
                'name' => $name,
                'objectives' => $objectives,
                'is_active' => 1,
                'is_library' => 1,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        return $result === false ? 0 : (int) $wpdb->insert_id;
    }

    public function update(
        int $id,
        int $categoryId,
        string $name,
        string $objectives = ''
    ): bool {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('sessions'),
            [
                'category_id' => $categoryId,
                'name' => $name,
                'objectives' => $objectives,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function exists(
        int $categoryId,
        string $name,
        ?int $excludedId = null
    ): bool {
        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM ' . Config::table('sessions') . '
            WHERE category_id = %d AND name = %s';
        $arguments = [$categoryId, $name];

        if ($excludedId !== null) {
            $sql .= ' AND id <> %d';
            $arguments[] = $excludedId;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$arguments)) > 0;
    }

    public function toggleActive(int $id): bool
    {
        global $wpdb;

        $table = Config::table('sessions');
        $currentValue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active FROM {$table} WHERE id = %d",
                $id
            )
        );

        if ($currentValue === null) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            [
                'is_active' => (int) $currentValue === 1 ? 0 : 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function duplicate(int $sessionId, string $newName): int
    {
        global $wpdb;

        $session = $this->find($sessionId);
        if ($session === null) {
            return 0;
        }

        $partsTable = Config::table('session_parts');
        $sessionExercisesTable = Config::table('session_exercises');

        $wpdb->query('START TRANSACTION');

        $newSessionId = $this->create(
            (int) $session['category_id'],
            $newName,
            (string) ($session['objectives'] ?? '')
        );

        if ($newSessionId <= 0) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $parts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$partsTable}
                WHERE session_id = %d
                ORDER BY position ASC, id ASC",
                $sessionId
            ),
            ARRAY_A
        );

        foreach ($parts as $part) {
            $partInserted = $wpdb->insert(
                $partsTable,
                [
                    'session_id' => $newSessionId,
                    'title' => $part['title'],
                    'position' => (int) $part['position'],
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%s']
            );

            if ($partInserted === false) {
                $wpdb->query('ROLLBACK');
                return 0;
            }

            $newPartId = (int) $wpdb->insert_id;
            $exercises = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$sessionExercisesTable}
                    WHERE part_id = %d
                    ORDER BY position ASC, id ASC",
                    (int) $part['id']
                ),
                ARRAY_A
            );

            foreach ($exercises as $exercise) {
                $exerciseInserted = $wpdb->insert(
                    $sessionExercisesTable,
                    [
                        'part_id' => $newPartId,
                        'exercise_id' => (int) $exercise['exercise_id'],
                        'position' => (int) $exercise['position'],
                        'duration' => $exercise['duration'] !== null
                            ? (int) $exercise['duration']
                            : null,
                        'coach_notes' => (string) ($exercise['coach_notes'] ?? ''),
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%d', '%s', '%s']
                );

                if ($exerciseInserted === false) {
                    $wpdb->query('ROLLBACK');
                    return 0;
                }
            }
        }

        $wpdb->query('COMMIT');

        return $newSessionId;
    }
}
