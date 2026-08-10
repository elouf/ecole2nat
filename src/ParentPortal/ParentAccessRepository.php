<?php

namespace Ecole2Nat\ParentPortal;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class ParentAccessRepository
{
    public function findSwimmer(int $swimmerId): ?array
    {
        global $wpdb;

        $swimmers = Config::table('swimmers');
        $groups = Config::table('groups');
        $categories = Config::table('categories');

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    swimmers.*,
                    groups.name AS group_name,
                    groups.category_id,
                    categories.name AS category_name
                FROM {$swimmers} AS swimmers
                LEFT JOIN {$groups} AS groups ON groups.id = swimmers.group_id
                LEFT JOIN {$categories} AS categories ON categories.id = groups.category_id
                WHERE swimmers.id = %d
                LIMIT 1",
                $swimmerId
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function codeHashExists(string $codeHash): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Config::table('swimmers') . '
                WHERE parent_access_code_hash = %s',
                $codeHash
            )
        );

        return $count > 0;
    }

    public function saveAccessCode(int $swimmerId, string $codeHash): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('swimmers'),
            [
                'parent_access_code_hash' => $codeHash,
                'parent_access_enabled' => 1,
                'parent_access_created_at' => current_time('mysql'),
                'parent_access_last_used_at' => null,
                'parent_access_count' => 0,
                'parent_access_distributed_at' => null,
                'parent_access_distribution_method' => null,
                'parent_access_distributed_to' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $swimmerId],
            ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function disableAccess(int $swimmerId): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('swimmers'),
            [
                'parent_access_enabled' => 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $swimmerId],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function saveParentMessage(int $swimmerId, string $message): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('swimmers'),
            [
                'parent_message' => $message,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $swimmerId],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function findByCodeHash(string $codeHash): ?array
    {
        global $wpdb;

        $swimmerId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . Config::table('swimmers') . '
                WHERE parent_access_code_hash = %s
                AND parent_access_enabled = 1
                AND is_active = 1
                LIMIT 1',
                $codeHash
            )
        );

        if ($swimmerId === null) {
            return null;
        }

        return $this->findSwimmer((int) $swimmerId);
    }

    public function markUsed(int $swimmerId): void
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Config::table('swimmers') . '
                SET parent_access_last_used_at = %s,
                    parent_access_count = parent_access_count + 1
                WHERE id = %d',
                current_time('mysql'),
                $swimmerId
            )
        );
    }

    public function logAttempt(?int $swimmerId, bool $success, string $ipHash): void
    {
        global $wpdb;

        $wpdb->insert(
            Config::table('parent_access_logs'),
            [
                'swimmer_id' => $swimmerId,
                'success' => $success ? 1 : 0,
                'ip_hash' => $ipHash,
                'attempted_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s']
        );
    }

    public function report(int $swimmerId): ?array
    {
        global $wpdb;

        $swimmer = $this->findSwimmer($swimmerId);

        if ($swimmer === null || empty($swimmer['category_id'])) {
            return null;
        }

        $domains = Config::table('skill_domains');
        $skills = Config::table('skills');
        $levels = Config::table('swimmer_skill_levels');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    domains.id AS domain_id,
                    domains.name AS domain_name,
                    domains.description AS domain_description,
                    skills.id AS skill_id,
                    skills.name AS skill_name,
                    skills.description AS skill_description,
                    COALESCE(levels.status, 'not_observed') AS status,
                    levels.evaluated_at
                FROM {$domains} AS domains
                INNER JOIN {$skills} AS skills
                    ON skills.domain_id = domains.id
                    AND skills.is_active = 1
                LEFT JOIN {$levels} AS levels
                    ON levels.skill_id = skills.id
                    AND levels.swimmer_id = %d
                WHERE domains.category_id = %d
                AND domains.is_active = 1
                ORDER BY domains.sort_order ASC, domains.name ASC,
                    skills.sort_order ASC, skills.name ASC",
                $swimmerId,
                (int) $swimmer['category_id']
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            $rows = [];
        }

        $groupedDomains = [];
        $latestUpdate = null;
        $counts = [
            'not_observed' => 0,
            'in_progress' => 0,
            'acquired' => 0,
        ];

        foreach ($rows as $row) {
            $domainId = (int) $row['domain_id'];
            $status = (string) $row['status'];

            if (!isset($counts[$status])) {
                $status = 'not_observed';
            }

            $counts[$status]++;

            if (!empty($row['evaluated_at']) && ($latestUpdate === null || $row['evaluated_at'] > $latestUpdate)) {
                $latestUpdate = $row['evaluated_at'];
            }

            if (!isset($groupedDomains[$domainId])) {
                $groupedDomains[$domainId] = [
                    'id' => $domainId,
                    'name' => $row['domain_name'],
                    'description' => $row['domain_description'],
                    'skills' => [],
                    'acquired_count' => 0,
                ];
            }

            if ($status === 'acquired') {
                $groupedDomains[$domainId]['acquired_count']++;
            }

            $groupedDomains[$domainId]['skills'][] = [
                'id' => (int) $row['skill_id'],
                'name' => $row['skill_name'],
                'description' => $row['skill_description'],
                'status' => $status,
            ];
        }

        return [
            'swimmer' => $swimmer,
            'domains' => array_values($groupedDomains),
            'counts' => $counts,
            'total' => array_sum($counts),
            'latest_update' => $latestUpdate,
        ];
    }

    public function distributionGroups(): array
    {
        global $wpdb;

        $groups = Config::table('groups');
        $seasons = Config::table('seasons');
        $categories = Config::table('categories');

        $results = $wpdb->get_results(
            "SELECT
                groups.*,
                seasons.name AS season_name,
                seasons.is_current,
                categories.name AS category_name
            FROM {$groups} AS groups
            INNER JOIN {$seasons} AS seasons ON seasons.id = groups.season_id
            INNER JOIN {$categories} AS categories ON categories.id = groups.category_id
            WHERE groups.is_active = 1
            ORDER BY seasons.is_current DESC, seasons.start_date DESC,
                categories.sort_order ASC, groups.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function swimmersByGroupForDistribution(int $groupId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Config::table('swimmers') . '
                WHERE group_id = %d
                AND is_active = 1
                ORDER BY last_name ASC, first_name ASC',
                $groupId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function markDistributed(
        int $swimmerId,
        string $method,
        string $recipient = ''
    ): bool {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('swimmers'),
            [
                'parent_access_distributed_at' => current_time('mysql'),
                'parent_access_distribution_method' => sanitize_key($method),
                'parent_access_distributed_to' => sanitize_text_field($recipient),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $swimmerId],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

}
