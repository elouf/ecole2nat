<?php

namespace Ecole2Nat\Category;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class CategoryRepository
{
    public function all(): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            'SELECT * FROM ' . Config::table('categories') . '
            ORDER BY sort_order ASC, name ASC',
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        string $name,
        string $description = '',
        int $sortOrder = 0
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('categories'),
            [
                'name'        => $name,
                'description' => $description,
                'sort_order'  => $sortOrder,
                'is_active'   => 1,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        return $result !== false;
    }

    public function toggleActive(int $id): bool
    {
        global $wpdb;

        $table = Config::table('categories');

        $currentValue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_active FROM {$table} WHERE id = %d",
                $id
            )
        );

        if ($currentValue === null) {
            return false;
        }

        $newValue = (int) $currentValue === 1 ? 0 : 1;

        $result = $wpdb->update(
            $table,
            [
                'is_active' => $newValue,
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
}