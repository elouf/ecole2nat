<?php

namespace Ecole2Nat\Performance;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

class PerformanceRepository
{
    public function saveTrainingTimed(int $groupId, int $seasonId, int $swimmerId, int $performanceId, array $data, int $userId): int
    {
        global $wpdb;
        $table = Config::table('training_performances');
        $now = current_time('mysql');
        if ($performanceId > 0) {
            $updated = $wpdb->update($table, $data + ['updated_by' => $userId, 'updated_at' => $now], [
                'id' => $performanceId, 'group_id' => $groupId, 'season_id' => $seasonId, 'swimmer_id' => $swimmerId,
            ]);
            return $updated === false ? 0 : $performanceId;
        }
        $inserted = $wpdb->insert($table, $data + [
            'group_id' => $groupId, 'season_id' => $seasonId, 'swimmer_id' => $swimmerId,
            'created_by' => $userId, 'created_at' => $now,
        ]);
        return $inserted === false ? 0 : (int) $wpdb->insert_id;
    }

    public function historyForSwimmer(int $swimmerId): array
    {
        global $wpdb;
        $training = Config::table('training_performances');
        $competition = Config::table('competition_performances');
        $competitions = Config::table('competitions');
        $groups = Config::table('groups');
        $users = $wpdb->users;
        $sql = "SELECT history.* FROM (
            SELECT training.id,'training' source,training.event_code,training.elapsed_time,training.comment,
                training.is_disqualified,training.time_rating,training.created_at performed_at,
                groups.name context_name,users.display_name coach_name
            FROM {$training} training
            LEFT JOIN {$groups} groups ON groups.id=training.group_id
            LEFT JOIN {$users} users ON users.ID=training.created_by
            WHERE training.swimmer_id=%d
            UNION ALL
            SELECT performance.id,'competition' source,performance.event_code,performance.elapsed_time,performance.comment,
                performance.is_disqualified,performance.time_rating,performance.created_at performed_at,
                competitions.name context_name,users.display_name coach_name
            FROM {$competition} performance
            INNER JOIN {$competitions} competitions ON competitions.id=performance.competition_id
            LEFT JOIN {$users} users ON users.ID=performance.created_by
            WHERE performance.swimmer_id=%d
        ) history ORDER BY history.performed_at DESC,history.id DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $swimmerId, $swimmerId), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function countsForSwimmers(array $swimmerIds): array
    {
        global $wpdb;
        $swimmerIds=array_values(array_unique(array_filter(array_map('absint',$swimmerIds))));
        if($swimmerIds===[])return [];
        $training=Config::table('training_performances');$competition=Config::table('competition_performances');
        $placeholders=implode(',',array_fill(0,count($swimmerIds),'%d'));
        $sql="SELECT swimmer_id,COUNT(*) total FROM (
            SELECT swimmer_id FROM {$training} WHERE elapsed_time IS NOT NULL AND elapsed_time<>'' AND swimmer_id IN ({$placeholders})
            UNION ALL
            SELECT swimmer_id FROM {$competition} WHERE elapsed_time IS NOT NULL AND elapsed_time<>'' AND swimmer_id IN ({$placeholders})
        ) performance_rows GROUP BY swimmer_id";
        $rows=$wpdb->get_results($wpdb->prepare($sql,...array_merge($swimmerIds,$swimmerIds)),ARRAY_A);
        $counts=[];foreach(is_array($rows)?$rows:[] as $row)$counts[(int)$row['swimmer_id']]=(int)$row['total'];
        return $counts;
    }

    public function competitionCountsForSwimmers(int $competitionId,array $swimmerIds): array
    {
        global $wpdb;
        $swimmerIds=array_values(array_unique(array_filter(array_map('absint',$swimmerIds))));
        if($competitionId<1||$swimmerIds===[])return [];
        $table=Config::table('competition_performances');$placeholders=implode(',',array_fill(0,count($swimmerIds),'%d'));
        $sql="SELECT swimmer_id,COUNT(*) total FROM {$table} WHERE competition_id=%d AND elapsed_time IS NOT NULL AND elapsed_time<>'' AND swimmer_id IN ({$placeholders}) GROUP BY swimmer_id";
        $rows=$wpdb->get_results($wpdb->prepare($sql,...array_merge([$competitionId],$swimmerIds)),ARRAY_A);
        $counts=[];foreach(is_array($rows)?$rows:[] as $row)$counts[(int)$row['swimmer_id']]=(int)$row['total'];
        return $counts;
    }

    public function deleteTrainingPerformance(int $groupId,int $seasonId,int $swimmerId,int $performanceId): bool
    {
        global $wpdb;
        return $wpdb->delete(Config::table('training_performances'),['id'=>$performanceId,'group_id'=>$groupId,'season_id'=>$seasonId,'swimmer_id'=>$swimmerId],['%d','%d','%d','%d'])===1;
    }

    public function trainingSeriesGroups(string $seriesKey): array
    {
        global $wpdb;
        $rows=$wpdb->get_col($wpdb->prepare('SELECT DISTINCT group_id FROM '.Config::table('training_performances').' WHERE series_key=%s',$seriesKey));
        return is_array($rows)?array_map('intval',$rows):[];
    }

    public function deleteTrainingSeries(string $seriesKey): int
    {
        global $wpdb;
        $deleted=$wpdb->delete(Config::table('training_performances'),['series_key'=>$seriesKey],['%s']);
        return $deleted===false?-1:(int)$deleted;
    }

    public function deleteForSwimmer(string $source, int $swimmerId, int $performanceId): bool
    {
        global $wpdb;
        $table = $source === 'competition'
            ? Config::table('competition_performances')
            : Config::table('training_performances');
        return $wpdb->delete($table, ['id' => $performanceId, 'swimmer_id' => $swimmerId], ['%d', '%d']) === 1;
    }

    public function purgeForSwimmer(int $swimmerId): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $training = $wpdb->delete(Config::table('training_performances'), ['swimmer_id' => $swimmerId], ['%d']);
        $competition = $training !== false
            ? $wpdb->delete(Config::table('competition_performances'), ['swimmer_id' => $swimmerId], ['%d'])
            : false;
        $success = $training !== false && $competition !== false;
        $wpdb->query($success ? 'COMMIT' : 'ROLLBACK');
        return $success;
    }
}
