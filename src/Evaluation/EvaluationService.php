<?php

namespace Ecole2Nat\Evaluation;

if (!defined('ABSPATH')) {
    exit;
}

class EvaluationService
{
    public const STATUS_NOT_OBSERVED = 'not_observed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ACQUIRED = 'acquired';

    private EvaluationRepository $repository;

    public function __construct()
    {
        $this->repository = new EvaluationRepository();
    }

    public function groups(): array
    {
        return $this->repository->groups();
    }

    public function groupContext(int $groupId): ?array
    {
        $group = $this->repository->findGroup($groupId);

        if ($group === null) {
            return null;
        }

        $skills = $this->repository->skillsByCategory(
            (int) $group['category_id']
        );

        $swimmers = $this->repository->swimmersByGroup($groupId);
        $skillCount = count($skills);

        foreach ($swimmers as &$swimmer) {
            $inProgress = (int) ($swimmer['in_progress_count'] ?? 0);
            $acquired = (int) ($swimmer['acquired_count'] ?? 0);

            $swimmer['not_observed_count'] = max(
                0,
                $skillCount - $inProgress - $acquired
            );
            $swimmer['skill_count'] = $skillCount;
        }
        unset($swimmer);

        return [
            'group' => $group,
            'skills' => $skills,
            'swimmers' => $swimmers,
        ];
    }

    public function swimmerEvaluation(
        int $groupId,
        int $swimmerId
    ): ?array {
        $context = $this->groupContext($groupId);

        if ($context === null) {
            return null;
        }

        $swimmer = $this->repository->findSwimmerInGroup(
            $swimmerId,
            $groupId
        );

        if ($swimmer === null) {
            return null;
        }

        $levels = $this->repository->levelsBySwimmer($swimmerId);

        foreach ($context['skills'] as &$skill) {
            $skillId = (int) $skill['id'];
            $savedLevel = $levels[$skillId] ?? null;

            $skill['status'] = is_array($savedLevel)
                ? (string) $savedLevel['status']
                : self::STATUS_NOT_OBSERVED;
            $skill['notes'] = is_array($savedLevel)
                ? (string) ($savedLevel['notes'] ?? '')
                : '';
            $skill['evaluated_at'] = is_array($savedLevel)
                ? ($savedLevel['evaluated_at'] ?? null)
                : null;
            $skill['evaluator_name'] = is_array($savedLevel)
                ? (string) ($savedLevel['evaluator_name'] ?? '')
                : '';
        }
        unset($skill);

        return [
            'group' => $context['group'],
            'swimmer' => $swimmer,
            'skills' => $context['skills'],
        ];
    }

    public function save(
        int $groupId,
        int $swimmerId,
        array $statuses,
        array $notes,
        int $userId
    ): array {
        $evaluation = $this->swimmerEvaluation($groupId, $swimmerId);

        if ($evaluation === null) {
            return [
                'success' => false,
                'message' => 'invalid',
            ];
        }

        $allowedStatuses = $this->statuses();
        $levels = [];

        foreach ($evaluation['skills'] as $skill) {
            $skillId = (int) $skill['id'];
            $status = isset($statuses[$skillId])
                ? sanitize_key((string) $statuses[$skillId])
                : self::STATUS_NOT_OBSERVED;

            if (!isset($allowedStatuses[$status])) {
                $status = self::STATUS_NOT_OBSERVED;
            }

            $levels[$skillId] = [
                'status' => $status,
                'notes' => isset($notes[$skillId])
                    ? sanitize_textarea_field((string) $notes[$skillId])
                    : '',
            ];
        }

        $saved = $this->repository->saveLevels(
            $swimmerId,
            $levels,
            $userId
        );

        return [
            'success' => $saved,
            'message' => $saved ? 'levels_saved' : 'error',
        ];
    }

    public function statuses(): array
    {
        return [
            self::STATUS_NOT_OBSERVED => __('Non observé', 'ecole2nat'),
            self::STATUS_IN_PROGRESS => __('En cours', 'ecole2nat'),
            self::STATUS_ACQUIRED => __('Acquis', 'ecole2nat'),
        ];
    }
}
