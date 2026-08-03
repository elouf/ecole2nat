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

        $results = $wpdb->get_results(
            "SELECT
                sessions.*,
                categories.name AS category_name
            FROM {$sessionsTable} AS sessions
            INNER JOIN {$categoriesTable} AS categories
                ON categories.id = sessions.category_id
            ORDER BY
                categories.sort_order ASC,
                sessions.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $categoryId,
        string $name,
        string $objectives = ''
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('sessions'),
            [
                'category_id' => $categoryId,
                'name' => $name,
                'objectives' => $objectives,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        return $result !== false;
    }

    public function exists(int $categoryId, string $name): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM " . Config::table('sessions') . "
                WHERE category_id = %d
                AND name = %s",
                $categoryId,
                $name
            )
        );

        return $count > 0;
    }

    public function toggleActive(int $id): bool
    {
        global $wpdb;

        $table = Config::table('sessions');

        $currentValue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active
                FROM {$table}
                WHERE id = %d",
                $id
            )
        );

        if ($currentValue === null) {
            return false;
        }

        $newValue = (int) $currentValue === 1 ? 0 : 1;

        $result = $wpdb->update(
            $table,
            [
                'is_active' => $newValue,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $result !== false;
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
                'name'        => $name,
                'objectives'  => $objectives,
                'updated_at'  => current_time('mysql'),
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $result !== false;
    }
}