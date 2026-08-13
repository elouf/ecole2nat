<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class CoachAccessRepository
{
    public function isTitular(int $userId, int $groupId): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Config::table('group_coaches') . ' WHERE user_id=%d AND group_id=%d',
                $userId,
                $groupId
            )
        ) > 0;
    }

    public function isSubstitute(int $userId, int $groupId, string $date): bool
    {
        global $wpdb;

        if (!$this->validDate($date)) {
            return false;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . Config::table('group_substitutions') .
                ' WHERE user_id=%d AND group_id=%d AND substitution_date=%s',
                $userId,
                $groupId,
                $date
            )
        ) > 0;
    }

    public function titularGroupIds(int $userId): array
    {
        global $wpdb;

        return array_map(
            'intval',
            $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT group_id FROM ' . Config::table('group_coaches') . ' WHERE user_id=%d',
                    $userId
                )
            )
        );
    }

    public function replaceAssignments(int $userId, array $groupIds): bool
    {
        global $wpdb;

        $table = Config::table('group_coaches');
        $wpdb->query('START TRANSACTION');

        if ($wpdb->delete($table, ['user_id' => $userId], ['%d']) === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        foreach (array_unique(array_map('intval', $groupIds)) as $groupId) {
            if ($groupId <= 0) {
                continue;
            }

            if ($wpdb->insert(
                $table,
                ['group_id' => $groupId, 'user_id' => $userId, 'created_at' => current_time('mysql')],
                ['%d', '%d', '%s']
            ) === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public function addSubstitution(int $userId, int $groupId, string $date, int $createdBy): bool
    {
        global $wpdb;

        if (!$this->validDate($date)) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user instanceof \WP_User || !user_can($user, 'e2n_coach_access')) {
            return false;
        }

        $groups = Config::table('groups');
        $seasons = Config::table('seasons');
        $groupExists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$groups} groups
                 INNER JOIN {$seasons} seasons ON seasons.id=groups.season_id
                 WHERE groups.id=%d AND groups.is_active=1 AND seasons.is_active=1",
                $groupId
            )
        ) > 0;
        if (!$groupExists) {
            return false;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                'INSERT INTO ' . Config::table('group_substitutions') .
                ' (group_id,user_id,substitution_date,created_by,created_at) VALUES (%d,%d,%s,%d,%s) ' .
                'ON DUPLICATE KEY UPDATE created_by=VALUES(created_by)',
                $groupId,
                $userId,
                $date,
                $createdBy,
                current_time('mysql')
            )
        );

        return $result !== false;
    }

    public function deleteSubstitution(int $id): bool
    {
        global $wpdb;

        return $id > 0 && $wpdb->delete(
            Config::table('group_substitutions'),
            ['id' => $id],
            ['%d']
        ) !== false;
    }

    public function substitutions(string $fromDate): array
    {
        global $wpdb;

        $substitutions = Config::table('group_substitutions');
        $groups = Config::table('groups');
        $seasons = Config::table('seasons');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT replacements.*, groups.name group_name, seasons.name season_name,
                        users.display_name user_name, users.user_email
                 FROM {$substitutions} replacements
                 INNER JOIN {$groups} groups ON groups.id=replacements.group_id
                 INNER JOIN {$seasons} seasons ON seasons.id=groups.season_id
                 INNER JOIN {$wpdb->users} users ON users.ID=replacements.user_id
                 WHERE replacements.substitution_date >= %s
                 ORDER BY replacements.substitution_date, groups.start_time, groups.name, users.display_name",
                $fromDate
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function clearUserAccess(int $userId): bool
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        if ($wpdb->delete(Config::table('group_coaches'), ['user_id' => $userId], ['%d']) === false
            || $wpdb->delete(Config::table('group_substitutions'), ['user_id' => $userId], ['%d']) === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');
        return true;
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }
}
