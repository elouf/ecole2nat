<?php

namespace Ecole2Nat\Performance;

if (!defined('ABSPATH')) { exit; }

class PerformanceService
{
    public function __construct(private ?PerformanceRepository $repository = null)
    {
        $this->repository ??= new PerformanceRepository();
    }

    public function saveTrainingTimed(int $groupId, int $seasonId, int $swimmerId, int $performanceId, array $input, int $userId): array
    {
        $event = strtoupper(sanitize_key((string) ($input['event_code'] ?? '')));
        $elapsed = sanitize_text_field((string) ($input['elapsed_time'] ?? ''));
        $rating = absint($input['time_rating'] ?? 0);
        $seriesKey = $this->seriesKey((string) ($input['series_key'] ?? ''));
        if ($groupId < 1 || $seasonId < 1 || $swimmerId < 1 || $seriesKey === '' || !EventCatalog::contains($event) || $rating > 5 || !preg_match('/^\d{1,3}:\d{2}\.\d{2}$/', $elapsed)) {
            return ['success' => false, 'message' => 'invalid'];
        }
        $id = $this->repository->saveTrainingTimed($groupId, $seasonId, $swimmerId, $performanceId, [
            'event_code' => $event,
            'series_key' => $seriesKey,
            'elapsed_time' => $elapsed,
            'comment' => sanitize_textarea_field((string) ($input['comment'] ?? '')),
            'is_disqualified' => !empty($input['is_disqualified']) ? 1 : 0,
            'time_rating' => $rating > 0 ? $rating : null,
        ], $userId);
        return ['success' => $id > 0, 'message' => $id > 0 ? 'saved' : 'error', 'performance_id' => $id];
    }

    public function historyForSwimmer(int $swimmerId): array { return $this->repository->historyForSwimmer($swimmerId); }
    public function countsForSwimmers(array $swimmerIds): array { return $this->repository->countsForSwimmers($swimmerIds); }
    public function competitionCountsForSwimmers(int $competitionId,array $swimmerIds): array { return $this->repository->competitionCountsForSwimmers($competitionId,$swimmerIds); }
    public function deleteTrainingPerformance(int $groupId,int $seasonId,int $swimmerId,int $performanceId): bool
    { return $performanceId>0&&$this->repository->deleteTrainingPerformance($groupId,$seasonId,$swimmerId,$performanceId); }
    public function trainingSeriesGroups(string $seriesKey): array
    { $seriesKey=$this->seriesKey($seriesKey);return $seriesKey===''?[]:$this->repository->trainingSeriesGroups($seriesKey); }
    public function deleteTrainingSeries(string $seriesKey): bool
    { $seriesKey=$this->seriesKey($seriesKey);return $seriesKey!==''&&$this->repository->deleteTrainingSeries($seriesKey)>0; }
    public function deleteForSwimmer(string $source,int $swimmerId,int $performanceId): bool
    { return in_array($source,['training','competition'],true)&&$swimmerId>0&&$performanceId>0&&$this->repository->deleteForSwimmer($source,$swimmerId,$performanceId); }
    public function purgeForSwimmer(int $swimmerId): bool
    { return $swimmerId>0&&$this->repository->purgeForSwimmer($swimmerId); }
    private function seriesKey(string $value): string
    { $value=strtolower(trim($value));return preg_match('/^[a-z0-9-]{12,36}$/',$value)?$value:''; }
}
