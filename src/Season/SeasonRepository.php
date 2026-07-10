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

        $results = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY start_date DESC, id DESC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(string $name): bool
    {
        global $wpdb;

        $result = $wpdb->insert(
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

        return $result !== false;
    }

    public function setCurrent(int $id): bool
    {
        global $wpdb;

        $table = Config::table('seasons');

        /*
         * wpdb::update() exige une clause WHERE.
         * Ici, nous voulons modifier toutes les lignes.
         */
        $resetResult = $wpdb->query(
            "UPDATE {$table} SET is_current = 0"
        );

        if ($resetResult === false) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            [
                'is_current' => 1,
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

    public function current(): ?array
    {
        global $wpdb;

        $table = Config::table('seasons');

        $season = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE is_current = 1 LIMIT 1",
            ARRAY_A
        );

        return is_array($season) ? $season : null;
    }
}