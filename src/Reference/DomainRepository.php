<?php

namespace Ecole2Nat\Reference;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class DomainRepository
{
    public function allByCategory(int $categoryId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM " . Config::table('skill_domains') . "
                 WHERE category_id = %d
                 ORDER BY sort_order, name",
                $categoryId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $categoryId,
        string $name,
        string $description = '',
        int $sortOrder = 0
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('skill_domains'),
            [
                'category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        return $result !== false;
    }
}