<?php

namespace Ecole2Nat\Exercise;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class ExerciseRepository
{
    public function all(): array
    {
        global $wpdb;

        $exercisesTable = Config::table('exercises');
        $skillsTable = Config::table('skills');
        $domainsTable = Config::table('skill_domains');
        $categoriesTable = Config::table('categories');

        $results = $wpdb->get_results(
            "SELECT
                exercises.*,
                skills.name AS skill_name,
                domains.name AS domain_name,
                categories.name AS category_name
            FROM {$exercisesTable} AS exercises
            INNER JOIN {$skillsTable} AS skills
                ON skills.id = exercises.skill_id
            INNER JOIN {$domainsTable} AS domains
                ON domains.id = skills.domain_id
            INNER JOIN {$categoriesTable} AS categories
                ON categories.id = domains.category_id
            ORDER BY
                categories.sort_order ASC,
                domains.sort_order ASC,
                skills.sort_order ASC,
                exercises.name ASC",
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function allBySkill(int $skillId): array
    {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT *
                FROM ' . Config::table('exercises') . '
                WHERE skill_id = %d
                ORDER BY name ASC',
                $skillId
            ),
            ARRAY_A
        );

        return is_array($results) ? $results : [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $exercise = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT *
                FROM ' . Config::table('exercises') . '
                WHERE id = %d
                LIMIT 1',
                $id
            ),
            ARRAY_A
        );

        return is_array($exercise) ? $exercise : null;
    }

    public function create(
        int $skillId,
        string $name,
        string $description = '',
        string $objectives = '',
        string $coachNotes = '',
        string $equipment = '',
        int $difficulty = 1
    ): bool {
        global $wpdb;

        $result = $wpdb->insert(
            Config::table('exercises'),
            [
                'skill_id'    => $skillId,
                'name'        => $name,
                'description' => $description,
                'objectives'  => $objectives,
                'coach_notes' => $coachNotes,
                'equipment'   => $equipment,
                'difficulty'  => $difficulty,
                'created_at'  => current_time('mysql'),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%s',
            ]
        );

        return $result !== false;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            Config::table('exercises'),
            [
                'skill_id'    => $data['skill_id'],
                'name'        => $data['name'],
                'description' => $data['description'],
                'objectives'  => $data['objectives'],
                'coach_notes' => $data['coach_notes'],
                'equipment'   => $data['equipment'],
                'difficulty'  => $data['difficulty'],
                'updated_at'  => current_time('mysql'),
            ],
            [
                'id' => $id,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
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
}