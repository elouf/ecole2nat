<?php

namespace Ecole2Nat\Session;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SessionPartRepository
{
    public function allBySession(int $sessionId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Config::table('session_parts') . '
                WHERE session_id = %d
                ORDER BY position ASC, id ASC',
                $sessionId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(int $sessionId, string $title): bool
    {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('session_parts'),
            [
                'session_id' => $sessionId,
                'title' => $title,
                'position' => $this->nextPosition($sessionId),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    public function update(int $id, string $title): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('session_parts'),
            [
                'title' => $title,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $partsTable = Config::table('session_parts');
        $sessionExercisesTable = Config::table('session_exercises');

        $wpdb->query('START TRANSACTION');

        $deletedExercises = $wpdb->delete(
            $sessionExercisesTable,
            ['part_id' => $id],
            ['%d']
        );

        if ($deletedExercises === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $deletedPart = $wpdb->delete(
            $partsTable,
            ['id' => $id],
            ['%d']
        );

        if ($deletedPart === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public function move(int $id, string $direction): bool
    {
        global $wpdb;

        $table = Config::table('session_parts');
        $current = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, session_id, position FROM {$table} WHERE id = %d",
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
                "SELECT id, position FROM {$table}
                WHERE session_id = %d AND position {$operator} %d
                ORDER BY position {$order}, id {$order}
                LIMIT 1",
                (int) $current['session_id'],
                (int) $current['position']
            ),
            ARRAY_A
        );

        if (!is_array($neighbour)) {
            return true;
        }

        $wpdb->query('START TRANSACTION');

        $first = $wpdb->update(
            $table,
            ['position' => (int) $neighbour['position'], 'updated_at' => current_time('mysql')],
            ['id' => (int) $current['id']],
            ['%d', '%s'],
            ['%d']
        );

        $second = $wpdb->update(
            $table,
            ['position' => (int) $current['position'], 'updated_at' => current_time('mysql')],
            ['id' => (int) $neighbour['id']],
            ['%d', '%s'],
            ['%d']
        );

        if ($first === false || $second === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public function exists(
        int $sessionId,
        string $title,
        ?int $excludedId = null
    ): bool {
        global $wpdb;

        $sql = 'SELECT COUNT(*) FROM ' . Config::table('session_parts') . '
            WHERE session_id = %d AND title = %s';
        $arguments = [$sessionId, $title];

        if ($excludedId !== null) {
            $sql .= ' AND id <> %d';
            $arguments[] = $excludedId;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$arguments)) > 0;
    }

    private function nextPosition(int $sessionId): int
    {
        global $wpdb;

        $maximum = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT MAX(position) FROM ' . Config::table('session_parts') . '
                WHERE session_id = %d',
                $sessionId
            )
        );

        return $maximum === null ? 1 : (int) $maximum + 1;
    }
}
