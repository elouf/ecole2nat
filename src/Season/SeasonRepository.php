<?php

namespace Ecole2Nat\Season;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SeasonRepository
{
    public function all(): array
    {
        global $wpdb;

        $table = Config::table('seasons');

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY start_date DESC",
            ARRAY_A
        );
    }

    public function create(string $name): bool
{
    global $wpdb;

    return (bool) $wpdb->insert(
        Config::table('seasons'),
        [
            'name'       => $name,
            'created_at' => current_time('mysql'),
        ],
        [
            '%s',
            '%s',
        ]
    );
}
}