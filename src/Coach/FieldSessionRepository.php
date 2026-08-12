<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class FieldSessionRepository
{
    public function attendance(int $groupId, string $date): array
    {
        global $wpdb;

        $table = Config::table('attendance');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT swimmer_id,status,recorded_at,recorded_by
                 FROM {$table}
                 WHERE group_id=%d AND session_date=%s",
                $groupId,
                $date
            ),
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(int) $row['swimmer_id']] = $row;
        }

        return $out;
    }

    public function saveAttendance(int $groupId, string $date, array $statuses, int $userId): bool
    {
        global $wpdb;

        if (!$this->validDate($date)) {
            return false;
        }

        $allowed = ['unknown', 'present', 'absent'];
        $allowedSwimmers = $this->allowedSwimmerIds($groupId);
        $table = Config::table('attendance');
        $now = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        foreach ($statuses as $swimmerId => $status) {
            $swimmerId = (int) $swimmerId;
            $status = sanitize_key((string) $status);

            if ($swimmerId <= 0 || !in_array($swimmerId, $allowedSwimmers, true) || !in_array($status, $allowed, true)) {
                continue;
            }

            if ($status === 'unknown') {
                $ok = $wpdb->delete(
                    $table,
                    [
                        'group_id' => $groupId,
                        'swimmer_id' => $swimmerId,
                        'session_date' => $date,
                    ],
                    ['%d', '%d', '%s']
                );
                if ($ok === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
                continue;
            }

            $sql = "INSERT INTO {$table}
                    (group_id,swimmer_id,session_date,status,recorded_by,recorded_at,created_at,updated_at)
                    VALUES (%d,%d,%s,%s,%d,%s,%s,%s)
                    ON DUPLICATE KEY UPDATE
                    status=VALUES(status),recorded_by=VALUES(recorded_by),recorded_at=VALUES(recorded_at),updated_at=VALUES(updated_at)";

            $ok = $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $groupId,
                    $swimmerId,
                    $date,
                    $status,
                    $userId,
                    $now,
                    $now,
                    $now
                )
            );

            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public function markSessionCompleted(int $groupId, string $date, int $userId, bool $completed): bool
    {
        global $wpdb;

        if (!$this->validDate($date)) {
            return false;
        }

        $table = Config::table('scheduled_sessions');
        $data = [
            'status' => $completed ? 'completed' : 'planned',
            'completed_at' => $completed ? current_time('mysql') : null,
            'completed_by' => $completed ? $userId : null,
            'updated_at' => current_time('mysql'),
        ];

        return $wpdb->update(
            $table,
            $data,
            ['group_id' => $groupId, 'session_date' => $date],
            ['%s', '%s', '%d', '%s'],
            ['%d', '%s']
        ) !== false;
    }


    private function allowedSwimmerIds(int $groupId): array
    {
        global $wpdb;
        $groups=Config::table('groups');
        $memberships=Config::table('swimmer_group_memberships');
        $rows=$wpdb->get_col($wpdb->prepare(
            "SELECT membership.swimmer_id
             FROM {$memberships} membership
             INNER JOIN {$groups} groups ON groups.id=membership.group_id AND groups.season_id=membership.season_id
             WHERE membership.group_id=%d",
            $groupId
        ));
        return array_map('intval', is_array($rows) ? $rows : []);
    }

    private function validDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}
