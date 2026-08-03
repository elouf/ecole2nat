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
                'SELECT *
                FROM ' . Config::table('session_parts') . '
                WHERE session_id = %d
                ORDER BY position ASC, id ASC',
                $sessionId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $sessionId,
        string $title,
        int $position = 0
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('session_parts'),
            [
                'session_id' => $sessionId,
                'title' => $title,
                'position' => $position,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
            ]
        );

        return $result !== false;
    }

    public function exists(int $sessionId, string $title): bool
    {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*)
                FROM ' . Config::table('session_parts') . '
                WHERE session_id = %d
                AND title = %s',
                $sessionId,
                $title
            )
        );

        return $count > 0;
    }
}