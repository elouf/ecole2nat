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
                "SELECT swimmers.*, groups.name AS group_name, groups.category_id,
                        categories.name AS category_name
                 FROM {$swimmers} swimmers
                 LEFT JOIN {$groups} groups ON groups.id = swimmers.group_id
                 LEFT JOIN {$categories} categories ON categories.id = groups.category_id
                 WHERE swimmers.id = %d LIMIT 1",
                $swimmerId
            ),
            ARRAY_A
        );

        return is_array($result) ? $result : null;
    }

    public function codeHashExists(string $codeHash): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Config::table('swimmers') . ' WHERE parent_access_code_hash = %s',
                $codeHash
            )
        ) > 0;
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
        return $wpdb->update(
            Config::table('swimmers'),
            ['parent_access_enabled' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $swimmerId],
            ['%d', '%s'],
            ['%d']
        ) !== false;
    }

    public function saveParentMessage(int $swimmerId, string $message): bool
    {
        global $wpdb;
        return $wpdb->update(
            Config::table('swimmers'),
            ['parent_message' => $message, 'updated_at' => current_time('mysql')],
            ['id' => $swimmerId],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }

    public function findByCodeHash(string $codeHash): ?array
    {
        global $wpdb;
        $swimmerId = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . Config::table('swimmers') . '
                 WHERE parent_access_code_hash = %s AND parent_access_enabled = 1 AND is_active = 1 LIMIT 1',
                $codeHash
            )
        );
        return $swimmerId === null ? null : $this->findSwimmer((int) $swimmerId);
    }

    public function markUsed(int $swimmerId): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . Config::table('swimmers') . '
                 SET parent_access_last_used_at = %s, parent_access_count = parent_access_count + 1
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

    public function reportSeasons(int $swimmerId): array
    {
        global $wpdb;
        $memberships = Config::table('swimmer_group_memberships');
        $groups = Config::table('groups');
        $seasons = Config::table('seasons');
        $categories = Config::table('categories');
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT seasons.id, seasons.name, seasons.start_date, seasons.end_date, seasons.is_current,
                        groups.id AS group_id, groups.name AS group_name,
                        categories.id AS category_id, categories.name AS category_name
                 FROM {$memberships} membership
                 INNER JOIN {$groups} groups ON groups.id = membership.group_id
                 INNER JOIN {$seasons} seasons ON seasons.id = membership.season_id
                 INNER JOIN {$categories} categories ON categories.id = groups.category_id
                 WHERE membership.swimmer_id = %d
                 ORDER BY seasons.is_current DESC, seasons.start_date DESC, seasons.id DESC",
                $swimmerId
            ),
            ARRAY_A
        );
        return is_array($results) ? $results : [];
    }

    public function report(int $swimmerId, int $seasonId = 0): ?array
    {
        global $wpdb;
        $swimmer = $this->findSwimmer($swimmerId);
        if ($swimmer === null) {
            return null;
        }

        $seasons = $this->reportSeasons($swimmerId);
        if ($seasons === []) {
            return null;
        }

        $selectedSeason = null;
        foreach ($seasons as $season) {
            if ($seasonId > 0 && (int) $season['id'] === $seasonId) {
                $selectedSeason = $season;
                break;
            }
        }
        if ($selectedSeason === null) {
            $selectedSeason = $seasons[0];
        }

        $seasonId = (int) $selectedSeason['id'];
        $categoryId = (int) $selectedSeason['category_id'];
        $swimmer['group_name'] = $selectedSeason['group_name'];
        $swimmer['category_id'] = $categoryId;
        $swimmer['category_name'] = $selectedSeason['category_name'];
        $swimmer['season_id'] = $seasonId;
        $swimmer['season_name'] = $selectedSeason['name'];

        $domains = Config::table('skill_domains');
        $skills = Config::table('skills');
        $seasonSkills = Config::table('season_skills');
        $levels = Config::table('swimmer_skill_levels');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT domains.id AS domain_id, domains.name AS domain_name,
                        domains.description AS domain_description,
                        skills.id AS skill_id, skills.name AS skill_name,
                        skills.description AS skill_description,
                        COALESCE(levels.status, 'not_observed') AS status,
                        levels.evaluated_at
                 FROM {$seasonSkills} season_skills
                 INNER JOIN {$skills} skills ON skills.id = season_skills.skill_id
                 INNER JOIN {$domains} domains ON domains.id = skills.domain_id
                 LEFT JOIN {$levels} levels
                    ON levels.skill_id = skills.id
                    AND levels.swimmer_id = %d
                    AND levels.season_id = %d
                 WHERE season_skills.season_id = %d
                 AND season_skills.is_active = 1
                 AND domains.category_id = %d
                 ORDER BY domains.sort_order ASC, domains.name ASC, skills.sort_order ASC, skills.name ASC",
                $swimmerId,
                $seasonId,
                $seasonId,
                $categoryId
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = [];
        }

        $groupedDomains = [];
        $latestUpdate = null;
        $counts = ['not_observed' => 0, 'in_progress' => 0, 'acquired' => 0];
        foreach ($rows as $row) {
            $domainId = (int) $row['domain_id'];
            $status = isset($counts[$row['status']]) ? (string) $row['status'] : 'not_observed';
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
            'season' => $selectedSeason,
            'seasons' => $seasons,
            'domains' => array_values($groupedDomains),
            'counts' => $counts,
            'total' => array_sum($counts),
            'latest_update' => $latestUpdate,
        ];
    }

    public function distributionCategories(): array
    {
        global $wpdb;
        $results = $wpdb->get_results(
            'SELECT id, name FROM ' . Config::table('categories') . '
             WHERE is_active = 1 ORDER BY sort_order ASC, name ASC',
            ARRAY_A
        );
        return is_array($results) ? $results : [];
    }

    public function distributionGroups(array $categoryIds = []): array
    {
        global $wpdb;
        $groups = Config::table('groups');
        $seasons = Config::table('seasons');
        $categories = Config::table('categories');
        $where = 'WHERE groups.is_active = 1';
        $params = [];
        $categoryIds = array_values(array_filter(array_unique(array_map('absint', $categoryIds))));
        if ($categoryIds !== []) {
            $where .= ' AND groups.category_id IN (' . implode(',', array_fill(0, count($categoryIds), '%d')) . ')';
            $params = $categoryIds;
        }
        $sql = "SELECT groups.*, seasons.name AS season_name, seasons.is_current,
                       categories.name AS category_name
                FROM {$groups} groups
                INNER JOIN {$seasons} seasons ON seasons.id = groups.season_id
                INNER JOIN {$categories} categories ON categories.id = groups.category_id
                {$where}
                ORDER BY seasons.is_current DESC, seasons.start_date DESC,
                         categories.sort_order ASC, groups.name ASC";
        $results = $params === []
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($results) ? $results : [];
    }

    public function swimmersForDistribution(array $filters = []): array
    {
        global $wpdb;
        $swimmers = Config::table('swimmers');
        $groups = Config::table('groups');
        $categories = Config::table('categories');
        $seasons = Config::table('seasons');

        $conditions = ['s.is_active = 1'];
        $params = [];
        $categoryIds = isset($filters['category_ids']) && is_array($filters['category_ids'])
            ? array_values(array_filter(array_unique(array_map('absint', $filters['category_ids']))))
            : [];
        if ($categoryIds !== []) {
            $conditions[] = 'g.category_id IN (' . implode(',', array_fill(0, count($categoryIds), '%d')) . ')';
            array_push($params, ...$categoryIds);
        }
        $groupId = isset($filters['group_id']) ? absint($filters['group_id']) : 0;
        if ($groupId > 0) {
            $conditions[] = 's.group_id = %d';
            $params[] = $groupId;
        }
        $accessStatus = sanitize_key((string) ($filters['access_status'] ?? 'all'));
        if ($accessStatus === 'missing') {
            $conditions[] = '(s.parent_access_code_hash IS NULL OR s.parent_access_code_hash = "" OR s.parent_access_enabled <> 1)';
        } elseif ($accessStatus === 'active') {
            $conditions[] = 's.parent_access_code_hash IS NOT NULL AND s.parent_access_code_hash <> "" AND s.parent_access_enabled = 1';
        } elseif ($accessStatus === 'sent') {
            $conditions[] = 's.parent_access_distributed_at IS NOT NULL';
        } elseif ($accessStatus === 'not_sent') {
            $conditions[] = 's.parent_access_distributed_at IS NULL';
        }
        $emailStatus = sanitize_key((string) ($filters['email_status'] ?? 'all'));
        if ($emailStatus === 'with') {
            $conditions[] = 's.responsible_email IS NOT NULL AND TRIM(s.responsible_email) <> ""';
        } elseif ($emailStatus === 'without') {
            $conditions[] = '(s.responsible_email IS NULL OR TRIM(s.responsible_email) = "")';
        }

        $sql = "SELECT s.*, g.name AS group_name, g.category_id, g.season_id,
                       c.name AS category_name, se.name AS season_name, se.is_current
                FROM {$swimmers} s
                INNER JOIN {$groups} g ON g.id = s.group_id
                INNER JOIN {$categories} c ON c.id = g.category_id
                INNER JOIN {$seasons} se ON se.id = g.season_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY se.is_current DESC, se.start_date DESC,
                         c.sort_order ASC, c.name ASC, g.name ASC,
                         s.last_name ASC, s.first_name ASC";
        $results = $params === []
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($results) ? $results : [];
    }

    public function swimmersByGroupForDistribution(int $groupId): array
    {
        return $this->swimmersForDistribution(['group_id' => $groupId]);
    }

    public function markDistributed(int $swimmerId, string $method, string $recipient = ''): bool
    {
        global $wpdb;
        return $wpdb->update(
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
        ) !== false;
    }
}
