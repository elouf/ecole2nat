<?php

namespace Ecole2Nat\Competition;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

class CompetitionRepository
{
    public function adminList(): array { global $wpdb; return $wpdb->get_results('SELECT c.*,s.name season_name FROM '.Config::table('competitions').' c LEFT JOIN '.Config::table('seasons').' s ON s.id=c.season_id ORDER BY c.start_date DESC',ARRAY_A)?:[]; }
    public function targetCategories(int $competitionId): array { global $wpdb; return array_map('strval',$wpdb->get_col($wpdb->prepare('SELECT category_name FROM '.Config::table('competition_target_categories').' WHERE competition_id=%d ORDER BY category_name',$competitionId))?:[]); }
    public function updateCompetition(int $id,array $data,array $categoryNames): bool
    {
        global $wpdb;$wpdb->query('START TRANSACTION');
        $ok=$wpdb->update(Config::table('competitions'),$data,['id'=>$id])!==false;
        if($ok)$ok=$wpdb->delete(Config::table('competition_target_categories'),['competition_id'=>$id],['%d'])!==false;
        $seen=[];
        foreach($categoryNames as $categoryName){if(!$ok)break;$key=$this->categoryKey($categoryName);if($key===''||isset($seen[$key]))continue;$seen[$key]=true;$ok=$wpdb->insert(Config::table('competition_target_categories'),['competition_id'=>$id,'category_name'=>$categoryName,'category_key'=>$key,'created_at'=>current_time('mysql')],['%d','%s','%s','%s'])!==false;}
        $wpdb->query($ok?'COMMIT':'ROLLBACK');return $ok;
    }
    public function forSwimmer(int $swimmerId): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            'SELECT DISTINCT c.*, r.response, r.comment, r.response_source, r.responded_at, r.parents_official, r.attendance_days, r.is_engaged,
                    i.id invoice_id,i.status invoice_status,i.invoice_number,i.current_version
             FROM ' . Config::table('competitions') . ' c
             INNER JOIN ' . Config::table('swimmer_group_memberships') . ' m ON m.season_id=c.season_id
             INNER JOIN ' . Config::table('swimmers') . ' s ON s.id=m.swimmer_id
             INNER JOIN ' . Config::table('groups') . ' g ON g.id=m.group_id
             LEFT JOIN ' . Config::table('swimmer_competition_category_states') . ' cs ON cs.id=(SELECT cs2.id FROM ' . Config::table('swimmer_competition_category_states') . ' cs2 WHERE cs2.swimmer_id=s.id AND cs2.effective_from<=c.start_date ORDER BY cs2.effective_from DESC,cs2.id DESC LIMIT 1)
             LEFT JOIN ' . Config::table('swimmer_competition_state_categories') . ' sc ON sc.state_id=cs.id
             LEFT JOIN ' . Config::table('competition_target_categories') . ' tc ON tc.competition_id=c.id AND tc.category_key=sc.category_key
             LEFT JOIN ' . Config::table('competition_registrations') . ' r ON r.competition_id=c.id AND r.swimmer_id=s.id
             LEFT JOIN ' . Config::table('competition_invoices') . ' i ON i.competition_id=c.id AND i.swimmer_id=s.id AND i.status IN (\'generated\',\'payment_declared\')
             WHERE s.id=%d AND s.is_active=1 AND c.status IN (\'published\',\'cancelled\') AND (c.start_date >= %s OR i.id IS NOT NULL)
             AND (c.target_all=1 OR tc.category_key IS NOT NULL)
             ORDER BY c.start_date ASC, c.name ASC',
            $swimmerId,
            current_time('Y-m-d')
        );
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function eligible(int $competitionId, int $swimmerId): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Config::table('competitions') . ' c
             INNER JOIN ' . Config::table('swimmer_group_memberships') . ' m ON m.season_id=c.season_id
             INNER JOIN ' . Config::table('swimmers') . ' s ON s.id=m.swimmer_id
             INNER JOIN ' . Config::table('groups') . ' g ON g.id=m.group_id
             LEFT JOIN ' . Config::table('swimmer_competition_category_states') . ' cs ON cs.id=(SELECT cs2.id FROM ' . Config::table('swimmer_competition_category_states') . ' cs2 WHERE cs2.swimmer_id=s.id AND cs2.effective_from<=c.start_date ORDER BY cs2.effective_from DESC,cs2.id DESC LIMIT 1)
             LEFT JOIN ' . Config::table('swimmer_competition_state_categories') . ' sc ON sc.state_id=cs.id
             LEFT JOIN ' . Config::table('competition_target_categories') . ' tc ON tc.competition_id=c.id AND tc.category_key=sc.category_key
             WHERE c.id=%d AND s.id=%d AND s.is_active=1 AND (c.target_all=1 OR tc.category_key IS NOT NULL)',
            $competitionId,
            $swimmerId
        )) > 0;
    }

    public function find(int $competitionId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Config::table('competitions') . ' WHERE id=%d', $competitionId), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function isEngaged(int $competitionId, int $swimmerId): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT is_engaged FROM ' . Config::table('competition_registrations') . ' WHERE competition_id=%d AND swimmer_id=%d LIMIT 1',
            $competitionId,
            $swimmerId
        )) === 1;
    }

    public function saveResponse(int $competitionId, int $swimmerId, string $response, string $comment, string $source, ?int $userId, ?bool $parentsOfficial = null, ?string $attendanceDays = null): bool
    {
        global $wpdb;
        $now = current_time('mysql');
        $sql = $wpdb->prepare(
            'INSERT INTO ' . Config::table('competition_registrations') . '
             (competition_id,swimmer_id,response,comment,response_source,responded_at,responded_by,parents_official,attendance_days,is_engaged,created_at,updated_at)
             VALUES (%d,%d,%s,%s,%s,%s,NULLIF(%d,0),NULLIF(%d,-1),NULLIF(%s,\'\'),0,%s,%s)
             ON DUPLICATE KEY UPDATE response=VALUES(response),comment=VALUES(comment),response_source=VALUES(response_source),responded_at=VALUES(responded_at),responded_by=VALUES(responded_by),parents_official=IF(VALUES(response_source)=\'parent\',VALUES(parents_official),parents_official),attendance_days=IF(VALUES(response_source)=\'parent\',VALUES(attendance_days),attendance_days),is_engaged=IF(VALUES(response)=\'yes\',is_engaged,0),engaged_at=IF(VALUES(response)=\'yes\',engaged_at,NULL),engaged_by=IF(VALUES(response)=\'yes\',engaged_by,NULL),updated_at=VALUES(updated_at)',
            $competitionId, $swimmerId, $response, $comment, $source, $now, $userId, $parentsOfficial === null ? -1 : ($parentsOfficial ? 1 : 0), $attendanceDays ?? '', $now, $now
        );
        return $wpdb->query($sql) !== false;
    }

    public function coachList(): array
    {
        global $wpdb;
        $sql = 'SELECT c.*, GROUP_CONCAT(DISTINCT tc.category_name ORDER BY tc.category_name SEPARATOR \' / \') competition_category_names
                FROM ' . Config::table('competitions') . ' c
                LEFT JOIN ' . Config::table('competition_target_categories') . ' tc ON tc.competition_id=c.id
                WHERE c.status<>\'draft\' AND c.start_date>=%s GROUP BY c.id ORDER BY c.start_date ASC';
        return $wpdb->get_results($wpdb->prepare($sql,current_time('Y-m-d')), ARRAY_A) ?: [];
    }

    public function coachDetail(int $competitionId): ?array
    {
        global $wpdb;
        $competition = $this->find($competitionId);
        if ($competition === null) return null;
        $competition['swimmers'] = $wpdb->get_results($wpdb->prepare(
            'SELECT DISTINCT s.id,s.first_name,s.last_name,s.licence_number,g.name group_name,r.response,r.comment,r.response_source,r.responded_at,r.parents_official,r.attendance_days,r.is_engaged,r.engaged_at,u.display_name engaged_by_name
             FROM ' . Config::table('competitions') . ' c
             INNER JOIN ' . Config::table('swimmer_group_memberships') . ' m ON m.season_id=c.season_id
             INNER JOIN ' . Config::table('swimmers') . ' s ON s.id=m.swimmer_id AND s.is_active=1
             INNER JOIN ' . Config::table('groups') . ' g ON g.id=m.group_id
             LEFT JOIN ' . Config::table('swimmer_competition_category_states') . ' cs ON cs.id=(SELECT cs2.id FROM ' . Config::table('swimmer_competition_category_states') . ' cs2 WHERE cs2.swimmer_id=s.id AND cs2.effective_from<=c.start_date ORDER BY cs2.effective_from DESC,cs2.id DESC LIMIT 1)
             LEFT JOIN ' . Config::table('swimmer_competition_state_categories') . ' sc ON sc.state_id=cs.id
             LEFT JOIN ' . Config::table('competition_target_categories') . ' tc ON tc.competition_id=c.id AND tc.category_key=sc.category_key
             LEFT JOIN ' . Config::table('competition_registrations') . ' r ON r.competition_id=c.id AND r.swimmer_id=s.id
             LEFT JOIN ' . $wpdb->users . ' u ON u.ID=r.engaged_by
             WHERE c.id=%d AND (c.target_all=1 OR tc.category_key IS NOT NULL)
             ORDER BY s.last_name,s.first_name', $competitionId
        ), ARRAY_A) ?: [];
        return $competition;
    }

    public function setEngaged(int $competitionId, int $swimmerId, bool $engaged, int $userId): bool
    {
        global $wpdb;
        return $wpdb->update(Config::table('competition_registrations'), [
            'is_engaged'=>$engaged ? 1 : 0,
            'engaged_at'=>$engaged ? current_time('mysql') : null,
            'engaged_by'=>$engaged ? $userId : null,
            'updated_at'=>current_time('mysql'),
        ], ['competition_id'=>$competitionId,'swimmer_id'=>$swimmerId,'response'=>'yes'], ['%d','%s','%d','%s'], ['%d','%d','%s']) !== false;
    }

    public function start(int $competitionId, int $userId, bool $forced): bool
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            $participants = Config::table('competition_participants');
            $registrations = Config::table('competition_registrations');
            $now = current_time('mysql');
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$participants} (competition_id,swimmer_id,added_manually,added_at,added_by)
                 SELECT competition_id,swimmer_id,0,%s,%d FROM {$registrations}
                 WHERE competition_id=%d AND response='yes' AND is_engaged=1",
                $now, $userId, $competitionId
            ));
            if ($inserted === false) throw new \RuntimeException('participants');
            if ($wpdb->update(Config::table('competitions'), ['started_at'=>$now,'started_by'=>$userId,'start_forced'=>$forced?1:0,'closed_at'=>null,'closed_by'=>null,'updated_at'=>$now], ['id'=>$competitionId], ['%s','%d','%d','%s','%d','%s'], ['%d']) === false) throw new \RuntimeException('competition');
            $wpdb->query('COMMIT'); return true;
        } catch (\Throwable $exception) { $wpdb->query('ROLLBACK'); return false; }
    }

    public function stop(int $competitionId): bool
    {
        global $wpdb;
        return $wpdb->update(Config::table('competitions'), ['started_at'=>null,'started_by'=>null,'start_forced'=>0,'closed_at'=>null,'closed_by'=>null,'updated_at'=>current_time('mysql')], ['id'=>$competitionId], ['%s','%d','%d','%s','%d','%s'], ['%d']) !== false;
    }

    public function close(int $competitionId,int $userId): bool
    { global $wpdb;return $wpdb->update(Config::table('competitions'),['closed_at'=>current_time('mysql'),'closed_by'=>$userId,'updated_at'=>current_time('mysql')],['id'=>$competitionId],['%s','%d','%s'],['%d'])!==false; }
    public function resume(int $competitionId): bool
    { global $wpdb;return $wpdb->update(Config::table('competitions'),['closed_at'=>null,'closed_by'=>null,'updated_at'=>current_time('mysql')],['id'=>$competitionId],['%s','%d','%s'],['%d'])!==false; }
    public function activeCompetitions(int $limit=10): array
    { global $wpdb;$limit=max(1,min(10,$limit));return $wpdb->get_results($wpdb->prepare('SELECT id,name FROM '.Config::table('competitions').' WHERE status=\'published\' AND started_at IS NOT NULL AND closed_at IS NULL ORDER BY started_at DESC LIMIT %d',$limit),ARRAY_A)?:[]; }

    public function unengagedYesNames(int $competitionId): array
    {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare('SELECT CONCAT(s.first_name,\' \',s.last_name) FROM '.Config::table('competition_registrations').' r INNER JOIN '.Config::table('swimmers').' s ON s.id=r.swimmer_id WHERE r.competition_id=%d AND r.response=\'yes\' AND r.is_engaged=0 ORDER BY s.last_name,s.first_name',$competitionId))?:[];
    }

    public function participants(int $competitionId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT s.id,s.first_name,s.last_name,s.gender,s.licence_number,g.name group_name,p.added_manually FROM '.Config::table('competition_participants').' p INNER JOIN '.Config::table('swimmers').' s ON s.id=p.swimmer_id LEFT JOIN '.Config::table('groups').' g ON g.id=s.group_id WHERE p.competition_id=%d ORDER BY s.last_name,s.first_name',$competitionId),ARRAY_A)?:[];
    }

    public function availableParticipants(int $competitionId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT DISTINCT s.id,s.first_name,s.last_name,g.name group_name FROM '.Config::table('competitions').' c INNER JOIN '.Config::table('swimmer_group_memberships').' m ON m.season_id=c.season_id INNER JOIN '.Config::table('swimmers').' s ON s.id=m.swimmer_id AND s.is_active=1 LEFT JOIN '.Config::table('groups').' g ON g.id=s.group_id LEFT JOIN '.Config::table('competition_participants').' p ON p.competition_id=c.id AND p.swimmer_id=s.id WHERE c.id=%d AND p.swimmer_id IS NULL ORDER BY s.last_name,s.first_name',$competitionId),ARRAY_A)?:[];
    }

    public function addParticipant(int $competitionId,int $swimmerId,int $userId): bool
    {
        global $wpdb;$participants=Config::table('competition_participants');$competitions=Config::table('competitions');$memberships=Config::table('swimmer_group_memberships');$swimmers=Config::table('swimmers');
        return $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$participants} (competition_id,swimmer_id,added_manually,added_at,added_by) SELECT c.id,s.id,1,%s,%d FROM {$competitions} c INNER JOIN {$memberships} m ON m.season_id=c.season_id INNER JOIN {$swimmers} s ON s.id=m.swimmer_id AND s.is_active=1 WHERE c.id=%d AND c.started_at IS NOT NULL AND s.id=%d LIMIT 1",current_time('mysql'),$userId,$competitionId,$swimmerId))===1;
    }

    public function performances(int $competitionId,int $swimmerId): array
    { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.Config::table('competition_performances').' WHERE competition_id=%d AND swimmer_id=%d ORDER BY id',$competitionId,$swimmerId),ARRAY_A)?:[]; }

    public function savePerformance(int $competitionId,int $swimmerId,int $performanceId,array $data,int $userId): bool
    {
        global $wpdb;$table=Config::table('competition_performances');$now=current_time('mysql');
        if($performanceId>0)return $wpdb->update($table,$data+['updated_by'=>$userId,'updated_at'=>$now],['id'=>$performanceId,'competition_id'=>$competitionId,'swimmer_id'=>$swimmerId])!==false;
        return $wpdb->insert($table,$data+['competition_id'=>$competitionId,'swimmer_id'=>$swimmerId,'created_by'=>$userId,'created_at'=>$now])!==false;
    }

    public function saveTimedPerformance(int $competitionId,int $swimmerId,int $performanceId,array $data,int $userId): int
    {
        global $wpdb;$table=Config::table('competition_performances');$now=current_time('mysql');
        if($performanceId>0){
            $updated=$wpdb->update($table,$data+['updated_by'=>$userId,'updated_at'=>$now],['id'=>$performanceId,'competition_id'=>$competitionId,'swimmer_id'=>$swimmerId]);
            return $updated===false?0:$performanceId;
        }
        return $wpdb->insert($table,$data+['competition_id'=>$competitionId,'swimmer_id'=>$swimmerId,'created_by'=>$userId,'created_at'=>$now])===false?0:(int)$wpdb->insert_id;
    }

    public function deletePerformance(int $competitionId,int $swimmerId,int $performanceId): bool
    {
        global $wpdb;
        return $wpdb->delete(Config::table('competition_performances'),['id'=>$performanceId,'competition_id'=>$competitionId,'swimmer_id'=>$swimmerId],['%d','%d','%d'])===1;
    }

    public function deleteSeries(int $competitionId,string $seriesKey): int
    {
        global $wpdb;
        $deleted=$wpdb->delete(Config::table('competition_performances'),['competition_id'=>$competitionId,'series_key'=>$seriesKey],['%d','%s']);
        return $deleted===false?-1:(int)$deleted;
    }

    public function isParticipant(int $competitionId,int $swimmerId): bool
    { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('competition_participants').' WHERE competition_id=%d AND swimmer_id=%d',$competitionId,$swimmerId))>0; }
    public function isStarted(int $competitionId): bool
    { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('competitions').' WHERE id=%d AND status=\'published\' AND started_at IS NOT NULL',$competitionId))>0; }

    private function categoryKey(string $value): string
    {
        $value=remove_accents(mb_strtolower(trim($value)));
        return trim((string)preg_replace('/[^a-z0-9]+/u',' ',$value));
    }
}
