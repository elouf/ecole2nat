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

        return $result !== false;
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
}