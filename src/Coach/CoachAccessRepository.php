<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class CoachAccessRepository
{
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
}
