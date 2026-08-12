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
                'is_active'  => 1,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s',
                '%d',
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
                'is_active' => 1,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%d',
                '%s',
            ],
            [
                '%d',
            ]
        );

        return $result !== false;
    }

    /**
     * Active/désactive une saison sans modifier l'état individuel de ses groupes.
     * La saison courante ne peut pas être désactivée.
     *
     * @return bool|string true en cas de succès, 'current' si blocage métier.
     */
    public function toggleActive(int $id): bool|string
    {
        global $wpdb;

        $table = Config::table('seasons');
        $season = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT is_active, is_current FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($season)) {
            return false;
        }

        $isActive = (int) ($season['is_active'] ?? 1) === 1;
        $isCurrent = (int) ($season['is_current'] ?? 0) === 1;

        if ($isActive && $isCurrent) {
            return 'current';
        }

        $result = $wpdb->update(
            $table,
            [
                'is_active' => $isActive ? 0 : 1,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function current(): ?array
    {
        global $wpdb;

        $table = Config::table('seasons');

        $season = $wpdb->get_row(
            "SELECT * FROM {$table} WHERE is_current = 1 AND is_active = 1 LIMIT 1",
            ARRAY_A
        );

        return is_array($season) ? $season : null;
    }
}