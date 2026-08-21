<?php

namespace Ecole2Nat\Admin\Deletion;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class EntityDeletionService
{
    public function delete(string $type, int $id): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'delete_invalid'];
        }

        return match ($type) {
            'category' => $this->deleteRestricted('categories', $id, [
                ['skill_domains', 'category_id', __('des domaines', 'ecole2nat')],
                ['groups', 'category_id', __('des groupes', 'ecole2nat')],
                ['sessions', 'category_id', __('des séances', 'ecole2nat')],
            ]),
            'domain' => $this->deleteRestricted('skill_domains', $id, [
                ['skills', 'domain_id', __('des compétences', 'ecole2nat')],
            ]),
            'skill' => $this->deleteRestricted('skills', $id, [
                ['exercises', 'skill_id', __('des exercices', 'ecole2nat')],
                ['swimmer_skill_levels', 'skill_id', __('des évaluations', 'ecole2nat')],
                ['skill_level_history', 'skill_id', __('de l’historique des progressions', 'ecole2nat')],
                ['season_skills', 'skill_id', __('des référentiels saisonniers', 'ecole2nat')],
            ]),
            'exercise' => $this->deleteRestricted('exercises', $id, [
                ['session_exercises', 'exercise_id', __('des séances', 'ecole2nat')],
            ]),
            'season' => $this->deleteSeason($id),
            'group' => $this->deleteRestricted('groups', $id, [
                ['swimmers', 'group_id', __('des nageurs', 'ecole2nat')],
                ['swimmer_group_memberships', 'group_id', __('des affectations historiques', 'ecole2nat')],
                ['group_coaches', 'group_id', __('des coachs titulaires', 'ecole2nat')],
                ['group_substitutions', 'group_id', __('des remplacements de coachs', 'ecole2nat')],
                ['scheduled_sessions', 'group_id', __('des séances planifiées', 'ecole2nat')],
                ['attendance', 'group_id', __('des pointages de présence', 'ecole2nat')],
            ]),
            'swimmer' => $this->deleteSwimmer($id),
            'session' => $this->deleteSession($id),
            'competition' => $this->deleteCompetition($id),
            default => ['success' => false, 'message' => 'delete_invalid'],
        };
    }

    private function deleteRestricted(string $table, int $id, array $dependencies): array
    {
        global $wpdb;

        foreach ($dependencies as [$dependencyTable, $column, $label]) {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . Config::table($dependencyTable) . " WHERE {$column} = %d",
                    $id
                )
            );

            if ($count > 0) {
                return [
                    'success' => false,
                    'message' => 'delete_blocked',
                    'reason' => sprintf(
                        __('Suppression impossible : cet élément est encore utilisé par %1$d %2$s.', 'ecole2nat'),
                        $count,
                        $label
                    ),
                ];
            }
        }

        $deleted = $wpdb->delete(Config::table($table), ['id' => $id], ['%d']);

        return [
            'success' => $deleted !== false && $deleted > 0,
            'message' => $deleted !== false && $deleted > 0 ? 'deleted' : 'error',
        ];
    }

    private function deleteSeason(int $id): array
    {
        global $wpdb;
        $current = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT is_current FROM ' . Config::table('seasons') . ' WHERE id = %d',
            $id
        ));
        if ($current === 1) {
            return ['success' => false, 'message' => 'delete_blocked', 'reason' => __('La saison courante ne peut pas être supprimée.', 'ecole2nat')];
        }
        return $this->deleteRestricted('seasons', $id, [
            ['groups', 'season_id', __('des groupes', 'ecole2nat')],
            ['season_skills', 'season_id', __('des compétences de référentiel', 'ecole2nat')],
            ['swimmer_group_memberships', 'season_id', __('des affectations de nageurs', 'ecole2nat')],
            ['swimmer_skill_levels', 'season_id', __('des évaluations', 'ecole2nat')],
            ['skill_level_history', 'season_id', __('de l’historique des progressions', 'ecole2nat')],
            ['competitions', 'season_id', __('des compétitions', 'ecole2nat')],
        ]);
    }

    private function deleteSwimmer(int $id): array
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->delete(Config::table('swimmer_skill_levels'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('skill_level_history'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('swimmer_group_memberships'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('parent_access_logs'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('attendance'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('competition_registrations'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('competition_performances'), ['swimmer_id' => $id], ['%d']);
            $wpdb->delete(Config::table('competition_participants'), ['swimmer_id' => $id], ['%d']);
            $stateIds = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Config::table('swimmer_competition_category_states') . ' WHERE swimmer_id = %d', $id)) ?: [];
            foreach ($stateIds as $stateId) {
                $wpdb->delete(Config::table('swimmer_competition_state_categories'), ['state_id' => (int) $stateId], ['%d']);
            }
            $wpdb->delete(Config::table('swimmer_competition_category_states'), ['swimmer_id' => $id], ['%d']);
            $deleted = $wpdb->delete(Config::table('swimmers'), ['id' => $id], ['%d']);
            if ($deleted === false || $deleted === 0) {
                throw new \RuntimeException('delete');
            }
            $wpdb->query('COMMIT');
            return ['success' => true, 'message' => 'deleted'];
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'message' => 'error'];
        }
    }

    private function deleteSession(int $id): array
    {
        global $wpdb;
        $parts = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . Config::table('session_parts') . ' WHERE session_id = %d',
            $id
        ));
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->delete(Config::table('scheduled_sessions'), ['session_id' => $id], ['%d']);
            foreach ($parts as $partId) {
                $wpdb->delete(Config::table('session_exercises'), ['part_id' => (int) $partId], ['%d']);
            }
            $wpdb->delete(Config::table('session_parts'), ['session_id' => $id], ['%d']);
            $deleted = $wpdb->delete(Config::table('sessions'), ['id' => $id], ['%d']);
            if ($deleted === false || $deleted === 0) {
                throw new \RuntimeException('delete');
            }
            $wpdb->query('COMMIT');
            return ['success' => true, 'message' => 'deleted'];
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'message' => 'error'];
        }
    }

    private function deleteCompetition(int $id): array
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');
        try {
            if ($wpdb->delete(Config::table('competition_performances'), ['competition_id' => $id], ['%d']) === false) {
                throw new \RuntimeException('performances');
            }
            if ($wpdb->delete(Config::table('competition_participants'), ['competition_id' => $id], ['%d']) === false) {
                throw new \RuntimeException('participants');
            }
            if ($wpdb->delete(Config::table('competition_registrations'), ['competition_id' => $id], ['%d']) === false) {
                throw new \RuntimeException('registrations');
            }
            if ($wpdb->delete(Config::table('competition_target_categories'), ['competition_id' => $id], ['%d']) === false) {
                throw new \RuntimeException('categories');
            }
            $deleted = $wpdb->delete(Config::table('competitions'), ['id' => $id], ['%d']);
            if ($deleted === false || $deleted === 0) {
                throw new \RuntimeException('competition');
            }
            $wpdb->query('COMMIT');
            return ['success' => true, 'message' => 'deleted'];
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            return ['success' => false, 'message' => 'delete_blocked', 'reason' => __('La compétition n’a pas pu être supprimée. Aucune donnée associée n’a été effacée.', 'ecole2nat')];
        }
    }
}
