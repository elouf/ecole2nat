<?php

namespace Ecole2Nat\Exercise;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

class ExerciseRepository
{
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

    public function create(
        int $skillId,
        string $name,
        string $description = '',
        string $objectives = '',
        string $coachNotes = '',
        string $equipment = '',
        ?int $duration = null,
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
                'duration'    => $duration,
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
}