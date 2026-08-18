<?php

namespace Ecole2Nat\Evaluation;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

class EvaluationRepository
{
    public function groups(): array
    {
        global $wpdb;
        $g=Config::table('groups'); $s=Config::table('seasons'); $c=Config::table('categories');
        $r=$wpdb->get_results("SELECT g.*, s.name season_name, s.is_current, c.name category_name
            FROM {$g} g INNER JOIN {$s} s ON s.id=g.season_id INNER JOIN {$c} c ON c.id=g.category_id
            WHERE g.is_active=1 AND s.is_active=1 ORDER BY s.is_current DESC,s.start_date DESC,c.sort_order ASC,g.name ASC",ARRAY_A);
        return is_array($r)?$r:[];
    }

    public function findGroup(int $groupId): ?array
    {
        global $wpdb;
        $g=Config::table('groups'); $s=Config::table('seasons'); $c=Config::table('categories');
        $r=$wpdb->get_row($wpdb->prepare("SELECT g.*,s.name season_name,c.name category_name FROM {$g} g
            INNER JOIN {$s} s ON s.id=g.season_id INNER JOIN {$c} c ON c.id=g.category_id
            WHERE g.id=%d AND g.is_active=1 AND s.is_active=1 LIMIT 1",$groupId),ARRAY_A);
        return is_array($r)?$r:null;
    }

    public function swimmersByGroup(int $groupId, int $seasonId): array
    {
        global $wpdb;
        $sw=Config::table('swimmers'); $lv=Config::table('swimmer_skill_levels'); $m=Config::table('swimmer_group_memberships');
        $sql="SELECT swimmers.*,COALESCE(summary.in_progress_count,0) in_progress_count,
                COALESCE(summary.acquired_count,0) acquired_count,summary.last_evaluated_at
              FROM {$m} membership
              INNER JOIN {$sw} swimmers ON swimmers.id=membership.swimmer_id
              LEFT JOIN (
                SELECT swimmer_id,SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) in_progress_count,
                SUM(CASE WHEN status='acquired' THEN 1 ELSE 0 END) acquired_count,MAX(evaluated_at) last_evaluated_at
                FROM {$lv} WHERE season_id=%d GROUP BY swimmer_id
              ) summary ON summary.swimmer_id=swimmers.id
              WHERE membership.group_id=%d AND membership.season_id=%d AND swimmers.is_active=1
              ORDER BY swimmers.last_name,swimmers.first_name";
        $r=$wpdb->get_results($wpdb->prepare($sql,$seasonId,$groupId,$seasonId),ARRAY_A);
        return is_array($r)?$r:[];
    }

    public function findSwimmerInGroup(int $swimmerId,int $groupId,int $seasonId): ?array
    {
        global $wpdb;
        $sw=Config::table('swimmers'); $m=Config::table('swimmer_group_memberships');
        $r=$wpdb->get_row($wpdb->prepare("SELECT swimmers.* FROM {$m} membership INNER JOIN {$sw} swimmers ON swimmers.id=membership.swimmer_id
            WHERE swimmers.id=%d AND membership.group_id=%d AND membership.season_id=%d LIMIT 1",$swimmerId,$groupId,$seasonId),ARRAY_A);
        return is_array($r)?$r:null;
    }

    public function skillsByCategory(int $categoryId,int $seasonId): array
    {
        global $wpdb;
        $d=Config::table('skill_domains'); $s=Config::table('skills'); $ss=Config::table('season_skills');
        $r=$wpdb->get_results($wpdb->prepare("SELECT skills.*,domains.name domain_name,domains.sort_order domain_sort_order
            FROM {$s} skills INNER JOIN {$d} domains ON domains.id=skills.domain_id
            INNER JOIN {$ss} season_skills ON season_skills.skill_id=skills.id AND season_skills.season_id=%d AND season_skills.is_active=1
            WHERE domains.category_id=%d AND domains.is_active=1 AND skills.is_active=1
            ORDER BY domains.sort_order,domains.name,skills.sort_order,skills.name",$seasonId,$categoryId),ARRAY_A);
        return is_array($r)?$r:[];
    }

    public function levelsBySwimmer(int $swimmerId,int $seasonId): array
    {
        global $wpdb;
        $l=Config::table('swimmer_skill_levels'); $u=$wpdb->users;
        $r=$wpdb->get_results($wpdb->prepare("SELECT levels.*,users.display_name evaluator_name FROM {$l} levels
            LEFT JOIN {$u} users ON users.ID=levels.evaluated_by WHERE levels.swimmer_id=%d AND levels.season_id=%d",$swimmerId,$seasonId),ARRAY_A);
        if(!is_array($r)) return [];
        $out=[]; foreach($r as $row)$out[(int)$row['skill_id']]=$row; return $out;
    }

    public function historyBySwimmer(int $swimmerId, int $seasonId): array
    {
        global $wpdb;
        $history = Config::table('skill_level_history');
        $users = $wpdb->users;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT history.*, users.display_name evaluator_name
                 FROM {$history} history
                 LEFT JOIN {$users} users ON users.ID=history.changed_by
                 WHERE history.swimmer_id=%d AND history.season_id=%d
                 ORDER BY history.changed_at DESC, history.id DESC",
                $swimmerId,
                $seasonId
            ),
            ARRAY_A
        );
        $grouped = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $grouped[(int) $row['skill_id']][] = $row;
        }
        return $grouped;
    }

    public function saveLevels(int $swimmerId,int $seasonId,array $levels,int $userId): bool
    {
        global $wpdb; $t=Config::table('swimmer_skill_levels'); $now=current_time('mysql'); $wpdb->query('START TRANSACTION');
        foreach($levels as $skillId=>$level){
            $previous=$this->currentStatus($swimmerId,$seasonId,(int)$skillId);
            $q=$wpdb->query($wpdb->prepare("INSERT INTO {$t} (swimmer_id,season_id,skill_id,status,evaluated_at,evaluated_by,notes,created_at,updated_at)
                VALUES (%d,%d,%d,%s,%s,%d,%s,%s,%s)
                ON DUPLICATE KEY UPDATE status=VALUES(status),evaluated_at=VALUES(evaluated_at),evaluated_by=VALUES(evaluated_by),notes=VALUES(notes),updated_at=VALUES(updated_at)",
                $swimmerId,$seasonId,(int)$skillId,$level['status'],$now,$userId,$level['notes'],$now,$now));
            if($q===false){$wpdb->query('ROLLBACK');return false;}
            if($previous!==$level['status']&&!$this->recordHistory($swimmerId,$seasonId,(int)$skillId,$previous,$level['status'],$now,$userId)){$wpdb->query('ROLLBACK');return false;}
        }
        $wpdb->query('COMMIT'); return true;
    }
    public function levelsBySkillForSwimmers(int $skillId, int $seasonId, array $swimmerIds): array
    {
        global $wpdb;
        $swimmerIds = array_values(array_filter(array_map('intval', $swimmerIds)));
        if ($swimmerIds === []) return [];
        $t = Config::table('swimmer_skill_levels');
        $placeholders = implode(',', array_fill(0, count($swimmerIds), '%d'));
        $params = array_merge([$skillId, $seasonId], $swimmerIds);
        $sql = "SELECT swimmer_id,status,notes,evaluated_at FROM {$t} WHERE skill_id=%d AND season_id=%d AND swimmer_id IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        $out=[];
        foreach (is_array($rows) ? $rows : [] as $row) $out[(int)$row['swimmer_id']]=$row;
        return $out;
    }

    public function saveStatusOnly(int $swimmerId,int $seasonId,int $skillId,string $status,int $userId): bool
    {
        global $wpdb;
        $t=Config::table('swimmer_skill_levels');
        $now=current_time('mysql');
        $previous=$this->currentStatus($swimmerId,$seasonId,$skillId);
        $wpdb->query('START TRANSACTION');
        $sql="INSERT INTO {$t} (swimmer_id,season_id,skill_id,status,evaluated_at,evaluated_by,notes,created_at,updated_at)
            VALUES (%d,%d,%d,%s,%s,%d,'',%s,%s)
            ON DUPLICATE KEY UPDATE status=VALUES(status),evaluated_at=VALUES(evaluated_at),evaluated_by=VALUES(evaluated_by),updated_at=VALUES(updated_at)";
        if($wpdb->query($wpdb->prepare($sql,$swimmerId,$seasonId,$skillId,$status,$now,$userId,$now,$now))===false){$wpdb->query('ROLLBACK');return false;}
        if($previous!==$status&&!$this->recordHistory($swimmerId,$seasonId,$skillId,$previous,$status,$now,$userId)){$wpdb->query('ROLLBACK');return false;}
        $wpdb->query('COMMIT');return true;
    }

    public function saveNoteOnly(int $swimmerId,int $seasonId,int $skillId,string $note,int $userId): bool
    {
        global $wpdb;
        $t=Config::table('swimmer_skill_levels');
        $now=current_time('mysql');
        $sql="INSERT INTO {$t} (swimmer_id,season_id,skill_id,status,evaluated_at,evaluated_by,notes,created_at,updated_at)
            VALUES (%d,%d,%d,'not_observed',%s,%d,%s,%s,%s)
            ON DUPLICATE KEY UPDATE notes=VALUES(notes),evaluated_at=VALUES(evaluated_at),evaluated_by=VALUES(evaluated_by),updated_at=VALUES(updated_at)";
        return $wpdb->query($wpdb->prepare($sql,$swimmerId,$seasonId,$skillId,$now,$userId,$note,$now,$now))!==false;
    }

    public function saveCollectiveSkill(int $seasonId, int $skillId, array $statuses, int $userId): bool
    {
        global $wpdb;
        $t=Config::table('swimmer_skill_levels');
        $now=current_time('mysql');
        $wpdb->query('START TRANSACTION');
        foreach($statuses as $swimmerId=>$status){
            $swimmerId=(int)$swimmerId;
            if($swimmerId<=0) continue;
            $previous=$this->currentStatus($swimmerId,$seasonId,$skillId);
            $sql="INSERT INTO {$t} (swimmer_id,season_id,skill_id,status,evaluated_at,evaluated_by,notes,created_at,updated_at)
                VALUES (%d,%d,%d,%s,%s,%d,'',%s,%s)
                ON DUPLICATE KEY UPDATE status=VALUES(status),evaluated_at=VALUES(evaluated_at),evaluated_by=VALUES(evaluated_by),updated_at=VALUES(updated_at)";
            $ok=$wpdb->query($wpdb->prepare($sql,$swimmerId,$seasonId,$skillId,$status,$now,$userId,$now,$now));
            if($ok===false){$wpdb->query('ROLLBACK');return false;}
            if($previous!==$status&&!$this->recordHistory($swimmerId,$seasonId,$skillId,$previous,$status,$now,$userId)){$wpdb->query('ROLLBACK');return false;}
        }
        $wpdb->query('COMMIT');
        return true;
    }

    private function currentStatus(int $swimmerId,int $seasonId,int $skillId): string
    {
        global $wpdb;
        $status=$wpdb->get_var($wpdb->prepare(
            'SELECT status FROM '.Config::table('swimmer_skill_levels').' WHERE swimmer_id=%d AND season_id=%d AND skill_id=%d LIMIT 1',
            $swimmerId,$seasonId,$skillId
        ));
        return is_string($status)&&$status!==''?$status:'not_observed';
    }

    private function recordHistory(int $swimmerId,int $seasonId,int $skillId,string $previous,string $status,string $changedAt,int $userId): bool
    {
        global $wpdb;
        return $wpdb->insert(Config::table('skill_level_history'),[
            'swimmer_id'=>$swimmerId,'season_id'=>$seasonId,'skill_id'=>$skillId,
            'previous_status'=>$previous,'status'=>$status,'changed_at'=>$changedAt,'changed_by'=>$userId,
        ],['%d','%d','%d','%s','%s','%s','%d'])!==false;
    }

}
