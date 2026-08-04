<?php

namespace Ecole2Nat\Evaluation;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class EvaluationRepository
{
    public function groups(): array
    {
        global $wpdb;

        $groupsTable = Config::table('groups');
        $seasonsTable = Config::table('seasons');
        $categoriesTable = Config::table('categories');

        $results = $wpdb->get_results(
            "SELECT
                groups.*,
                seasons.name AS season_name,
                seasons.is_current,
                categories.name AS category_name
            FROM {$groupsTable} AS groups
            INNER JOIN {$seasonsTable} AS seasons
                ON seasons.id = groups.season_id
            INNER JOIN {$categoriesTable} AS categories
                ON categories.id = groups.category_id
            WHERE groups.is_active = 1
            ORDER BY
                seasons.is_current DESC,
                seasons.start_date DESC,
                categories.sort_order ASC,
                groups.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function findGroup(int $groupId): ?array
    {
        global $wpdb;

        $groupsTable = Config::table('groups');
        $seasonsTable = Config::table('seasons');
        $categoriesTable = Config::table('categories');

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    groups.*,
                    seasons.name AS season_name,
                    categories.name AS category_name
                FROM {$groupsTable} AS groups
                INNER JOIN {$seasonsTable} AS seasons
                    ON seasons.id = groups.season_id
                INNER JOIN {$categoriesTable} AS categories
                    ON categories.id = groups.category_id
                WHERE groups.id = %d
                LIMIT 1",
                $groupId
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function swimmersByGroup(int $groupId): array
    {
        global $wpdb;

        $swimmersTable = Config::table('swimmers');
        $levelsTable = Config::table('swimmer_skill_levels');

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    swimmers.*,
                    COALESCE(summary.in_progress_count, 0) AS in_progress_count,
                    COALESCE(summary.acquired_count, 0) AS acquired_count,
                    summary.last_evaluated_at
                FROM {$swimmersTable} AS swimmers
                LEFT JOIN (
                    SELECT
                        swimmer_id,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                        SUM(CASE WHEN status = 'acquired' THEN 1 ELSE 0 END) AS acquired_count,
                        MAX(evaluated_at) AS last_evaluated_at
                    FROM {$levelsTable}
                    GROUP BY swimmer_id
                ) AS summary
                    ON summary.swimmer_id = swimmers.id
                WHERE swimmers.group_id = %d
                AND swimmers.is_active = 1
                ORDER BY swimmers.last_name ASC, swimmers.first_name ASC",
                $groupId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function findSwimmerInGroup(int $swimmerId, int $groupId): ?array
    {
        global $wpdb;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT *
                FROM ' . Config::table('swimmers') . '
                WHERE id = %d
                AND group_id = %d
                LIMIT 1',
                $swimmerId,
                $groupId
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function skillsByCategory(int $categoryId): array
    {
        global $wpdb;

        $domainsTable = Config::table('skill_domains');
        $skillsTable = Config::table('skills');

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    skills.*,
                    domains.name AS domain_name,
                    domains.sort_order AS domain_sort_order
                FROM {$skillsTable} AS skills
                INNER JOIN {$domainsTable} AS domains
                    ON domains.id = skills.domain_id
                WHERE domains.category_id = %d
                AND domains.is_active = 1
                AND skills.is_active = 1
                ORDER BY
                    domains.sort_order ASC,
                    domains.name ASC,
                    skills.sort_order ASC,
                    skills.name ASC",
                $categoryId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function levelsBySwimmer(int $swimmerId): array
    {
        global $wpdb;

        $levelsTable = Config::table('swimmer_skill_levels');
        $usersTable = $wpdb->users;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    levels.*,
                    users.display_name AS evaluator_name
                FROM {$levelsTable} AS levels
                LEFT JOIN {$usersTable} AS users
                    ON users.ID = levels.evaluated_by
                WHERE levels.swimmer_id = %d",
                $swimmerId
            ),
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        $levels = [];

        foreach ($results as $result) {
            $levels[(int) $result['skill_id']] = $result;
        }

        return $levels;
    }

    public function saveLevels(
        int $swimmerId,
        array $levels,
        int $userId
    ): bool {
        global $wpdb;

        $table = Config::table('swimmer_skill_levels');
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        foreach ($levels as $skillId => $level) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (
                        swimmer_id,
                        skill_id,
                        status,
                        evaluated_at,
                        evaluated_by,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (%d, %d, %s, %s, %d, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        evaluated_at = VALUES(evaluated_at),
                        evaluated_by = VALUES(evaluated_by),
                        notes = VALUES(notes),
                        updated_at = VALUES(updated_at)",
                    $swimmerId,
                    (int) $skillId,
                    $level['status'],
                    $now,
                    $userId,
                    $level['notes'],
                    $now,
                    $now
                )
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');

                return false;
            }
        }

        $wpdb->query('COMMIT');

        return true;
    }
}
