<?php

namespace Ecole2Nat\Group;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class GroupRepository
{
    public function all(): array
    {
        global $wpdb;

        $groupsTable = Config::table('groups');
        $seasonsTable = Config::table('seasons');
        $categoriesTable = Config::table('categories');

        $results = $wpdb->get_results(
            "SELECT
                g.*,
                s.name AS season_name,
                s.is_active AS season_is_active,
                c.name AS category_name
            FROM {$groupsTable} g
            INNER JOIN {$seasonsTable} s
                ON s.id = g.season_id
            INNER JOIN {$categoriesTable} c
                ON c.id = g.category_id
            ORDER BY
                s.start_date DESC,
                c.sort_order ASC,
                g.weekday ASC,
                g.start_time ASC,
                g.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $seasonId,
        int $categoryId,
        string $name,
        string $color = '',
        ?int $weekday = null,
        ?string $startTime = null,
        ?string $endTime = null
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('groups'),
            [
                'season_id'   => $seasonId,
                'category_id' => $categoryId,
                'name'        => $name,
                'color'       => $color !== '' ? $color : null,
                'weekday'     => $weekday,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'is_active'   => 1,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            wp_die(
                '<strong>Erreur SQL :</strong><br>' .
                esc_html($wpdb->last_error) .
                '<br><br><strong>Requête :</strong><br>' .
                esc_html($wpdb->last_query)
            );
        }

        return true;
    }

    public function toggleActive(int $id): bool
    {
        global $wpdb;

        $table = Config::table('groups');

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

    public function exists(int $seasonId, string $name): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM " . Config::table('groups') . "
                WHERE season_id = %d
                AND name = %s",
                $seasonId,
                $name
            )
        );

        return $count > 0;
    }

    public function active(): array
    {
        global $wpdb;

        $table = Config::table('groups');
        $seasons = Config::table('seasons');

        $results = $wpdb->get_results(
            "
            SELECT g.*
            FROM {$table} g
            INNER JOIN {$seasons} s ON s.id = g.season_id
            WHERE g.is_active = 1
              AND s.is_active = 1
            ORDER BY g.name ASC
            ",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }
}