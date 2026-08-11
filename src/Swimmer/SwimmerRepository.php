<?php

namespace Ecole2Nat\Swimmer;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SwimmerRepository
{
    public function all(): array
    {
        global $wpdb;

        $tableSwimmers = $wpdb->prefix . 'e2n_swimmers';
        $tableGroups   = $wpdb->prefix . 'e2n_groups';

        $results = $wpdb->get_results(
            "
            SELECT
                s.*,
                g.name AS group_name
            FROM {$tableSwimmers} s
            LEFT JOIN {$tableGroups} g
                ON g.id = s.group_id
            ORDER BY
                s.last_name ASC,
                s.first_name ASC
            ",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(array $data): bool
    {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('swimmers'),
            [
                'group_id'           => $data['group_id'] ?: null,
                'last_name'          => $data['last_name'],
                'first_name'         => $data['first_name'],
                'birth_date'         => $data['birth_date'],
                'gender'             => $data['gender'],
                'responsible_name'   => $data['responsible_name'],
                'responsible_email'  => $data['responsible_email'],
                'responsible_phone'  => $data['responsible_phone'],
                'licence_number'     => $data['licence_number'],
                'registration_date'  => $data['registration_date'],
                'medical_note'       => $data['medical_note'],
                'is_active'          => 1,
                'created_at'         => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            return false;
        }
        return $this->syncMembership((int) $wpdb->insert_id, (int) ($data['group_id'] ?? 0));
    }

    public function toggleActive(int $id): bool
    {
        global $wpdb;

        $table = Config::table('swimmers');

        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active
                 FROM {$table}
                 WHERE id = %d",
                $id
            )
        );

        if ($current === null) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            [
                'is_active' => (int) $current === 1 ? 0 : 1,
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

    public function exists(
        string $lastName,
        string $firstName,
        ?string $birthDate
    ): bool {
        global $wpdb;

        $table = Config::table('swimmers');

        if ($birthDate === null || $birthDate === '') {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$table}
                    WHERE last_name = %s
                    AND first_name = %s
                    AND birth_date IS NULL",
                    $lastName,
                    $firstName
                )
            );
        } else {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$table}
                    WHERE last_name = %s
                    AND first_name = %s
                    AND birth_date = %s",
                    $lastName,
                    $firstName,
                    $birthDate
                )
            );
        }

        return $count > 0;
    }

    public function find(int $id): ?array
{
    global $wpdb;

    $result = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT *
            FROM ' . Config::table('swimmers') . '
            WHERE id = %d
            LIMIT 1',
            $id
        ),
        ARRAY_A
    );

    return is_array($result) ? $result : null;
}

public function update(int $id, array $data): bool
{
    global $wpdb;

    $result = $wpdb->update(
        Config::table('swimmers'),
        [
            'group_id'           => $data['group_id'],
            'last_name'          => $data['last_name'],
            'first_name'         => $data['first_name'],
            'birth_date'         => $data['birth_date'],
            'gender'             => $data['gender'],
            'responsible_name'   => $data['responsible_name'],
            'responsible_email'  => $data['responsible_email'],
            'responsible_phone'  => $data['responsible_phone'],
            'licence_number'     => $data['licence_number'],
            'registration_date'  => $data['registration_date'],
            'medical_note'       => $data['medical_note'],
            'updated_at'         => current_time('mysql'),
        ],
        [
            'id' => $id,
        ],
        [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ],
        [
            '%d',
        ]
    );

    if ($result === false) {
        return false;
    }
    return $this->syncMembership($id, (int) ($data['group_id'] ?? 0));
}

    private function syncMembership(int $swimmerId, int $groupId): bool
    {
        global $wpdb;
        if ($groupId <= 0) {
            return true;
        }
        $seasonId = (int) $wpdb->get_var(
            $wpdb->prepare('SELECT season_id FROM ' . Config::table('groups') . ' WHERE id = %d', $groupId)
        );
        if ($seasonId <= 0) {
            return false;
        }
        $table = Config::table('swimmer_group_memberships');
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (swimmer_id, season_id, group_id, created_at, updated_at)
             VALUES (%d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE group_id = VALUES(group_id), updated_at = VALUES(updated_at)",
            $swimmerId, $seasonId, $groupId, current_time('mysql'), current_time('mysql')
        );
        return $wpdb->query($sql) !== false;
    }

    public function search(SwimmerSearchCriteria $criteria): array
    {
        global $wpdb;

        $swimmers = Config::table('swimmers');
        $groups = Config::table('groups');
        $categories = Config::table('categories');
        $seasons = Config::table('seasons');

        [$where, $params] = $this->buildSearchWhere($criteria);
        $allowedOrder = [
            'last_name' => 's.last_name',
            'first_name' => 's.first_name',
            'birth_date' => 's.birth_date',
            'group_name' => 'g.name',
            'registration_date' => 's.registration_date',
        ];
        $orderBy = $allowedOrder[$criteria->orderBy] ?? 's.last_name';
        $order = strtolower($criteria->order) === 'desc' ? 'DESC' : 'ASC';
        $offset = max(0, ($criteria->page - 1) * $criteria->perPage);

        $sql = "SELECT s.*, g.name AS group_name, g.category_id, g.season_id,
                       c.name AS category_name, se.name AS season_name
                FROM {$swimmers} s
                LEFT JOIN {$groups} g ON g.id = s.group_id
                LEFT JOIN {$categories} c ON c.id = g.category_id
                LEFT JOIN {$seasons} se ON se.id = g.season_id
                {$where}
                ORDER BY {$orderBy} {$order}, s.last_name ASC, s.first_name ASC
                LIMIT %d OFFSET %d";
        $params[] = $criteria->perPage;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($results) ? $results : [];
    }

    public function countSearch(SwimmerSearchCriteria $criteria): int
    {
        global $wpdb;
        [$where, $params] = $this->buildSearchWhere($criteria);
        $sql = 'SELECT COUNT(*) FROM ' . Config::table('swimmers') . ' s '
            . 'LEFT JOIN ' . Config::table('groups') . ' g ON g.id = s.group_id '
            . 'LEFT JOIN ' . Config::table('categories') . ' c ON c.id = g.category_id '
            . 'LEFT JOIN ' . Config::table('seasons') . " se ON se.id = g.season_id {$where}";

        return $params === []
            ? (int) $wpdb->get_var($sql)
            : (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    private function buildSearchWhere(SwimmerSearchCriteria $criteria): array
    {
        global $wpdb;
        $conditions = [];
        $params = [];

        if ($criteria->search !== '') {
            $like = '%' . $wpdb->esc_like($criteria->search) . '%';
            $conditions[] = '(s.last_name LIKE %s OR s.first_name LIKE %s OR s.licence_number LIKE %s OR s.responsible_name LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($criteria->groupId > 0) {
            $conditions[] = 's.group_id = %d';
            $params[] = $criteria->groupId;
        }
        if ($criteria->categoryId > 0) {
            $conditions[] = 'g.category_id = %d';
            $params[] = $criteria->categoryId;
        }
        if ($criteria->seasonId > 0) {
            $conditions[] = 'g.season_id = %d';
            $params[] = $criteria->seasonId;
        }
        if ($criteria->status === 'active' || $criteria->status === 'inactive') {
            $conditions[] = 's.is_active = %d';
            $params[] = $criteria->status === 'active' ? 1 : 0;
        }
        if ($criteria->assignment === 'assigned') {
            $conditions[] = 's.group_id IS NOT NULL';
        } elseif ($criteria->assignment === 'unassigned') {
            $conditions[] = 's.group_id IS NULL';
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $params];
    }

}