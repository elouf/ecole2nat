<?php

namespace Ecole2Nat\Reference;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class SkillRepository
{
    public function allByDomain(int $domainId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT *
                FROM ' . Config::table('skills') . '
                WHERE domain_id = %d
                ORDER BY sort_order ASC, name ASC',
                $domainId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function allByCategory(int $categoryId): array
    {
        global $wpdb;

        $domainsTable = Config::table('skill_domains');
        $skillsTable = Config::table('skills');

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT skills.*
                FROM {$skillsTable} AS skills
                INNER JOIN {$domainsTable} AS domains
                    ON domains.id = skills.domain_id
                WHERE domains.category_id = %d
                ORDER BY domains.sort_order ASC,
                    domains.name ASC,
                    skills.sort_order ASC,
                    skills.name ASC",
                $categoryId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function create(
        int $domainId,
        string $name,
        string $description = '',
        int $sortOrder = 0
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('skills'),
            [
                'domain_id'  => $domainId,
                'name'       => $name,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active'  => 1,
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

        if ($result === false) {
            return false;
        }

        $skillId = (int) $wpdb->insert_id;
        $seasonId = (int) $wpdb->get_var(
            'SELECT id FROM ' . Config::table('seasons') . ' WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
        );
        if ($seasonId > 0) {
            $wpdb->insert(
                Config::table('season_skills'),
                [
                    'season_id' => $seasonId,
                    'skill_id' => $skillId,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s']
            );
        }

        return true;
    }
}