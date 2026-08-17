<?php

namespace Ecole2Nat\Coach;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) {
    exit;
}

final class CoachSessionEditorRepository
{
    public function createForSlot(int $groupId, string $date, string $name, string $objectives, int $userId, int $sourceSessionId = 0): int
    {
        global $wpdb;

        $group = $this->group($groupId);
        if ($group === null || !$this->validDate($date)) {
            return 0;
        }

        $source = null;
        if ($sourceSessionId > 0) {
            $source = $this->session($sourceSessionId);
            if ($source === null || (int) $source['category_id'] !== (int) $group['category_id']) {
                return 0;
            }
        }

        $name = $this->uniqueName((int) $group['category_id'], $name);
        if ($name === '') {
            return 0;
        }

        $sessions = Config::table('sessions');
        $scheduled = Config::table('scheduled_sessions');
        $parts = Config::table('session_parts');
        $items = Config::table('session_exercises');
        $now = current_time('mysql');
        $wpdb->query('START TRANSACTION');

        $created = $wpdb->insert($sessions, [
            'category_id' => (int) $group['category_id'],
            'name' => $name,
            'objectives' => $objectives,
            'is_active' => 1,
            'created_at' => $now,
        ], ['%d', '%s', '%s', '%d', '%s']);
        if ($created === false) {
            $wpdb->query('ROLLBACK');
            return 0;
        }
        $sessionId = (int) $wpdb->insert_id;

        if ($source !== null && !$this->copyContents($sourceSessionId, $sessionId, $parts, $items, $now)) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $sql = "INSERT INTO {$scheduled}
                (group_id,session_id,session_date,status,created_by,coach_editable_copy,created_at,updated_at)
                VALUES (%d,%d,%s,'planned',%d,1,%s,%s)
                ON DUPLICATE KEY UPDATE session_id=VALUES(session_id),status='planned',completed_by=NULL,
                completed_at=NULL,coach_editable_copy=1,created_by=VALUES(created_by),updated_at=VALUES(updated_at)";
        if ($wpdb->query($wpdb->prepare($sql, $groupId, $sessionId, $date, $userId, $now, $now)) === false) {
            $wpdb->query('ROLLBACK');
            return 0;
        }

        $wpdb->query('COMMIT');
        return $sessionId;
    }

    public function editor(int $groupId, string $date, int $sessionId): ?array
    {
        global $wpdb;

        if (!$this->isEditableContext($groupId, $date, $sessionId)) {
            return null;
        }

        $session = $this->session($sessionId);
        if ($session === null) {
            return null;
        }

        $partsTable = Config::table('session_parts');
        $itemsTable = Config::table('session_exercises');
        $exercises = Config::table('exercises');
        $skills = Config::table('skills');
        $domains = Config::table('skill_domains');

        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$partsTable} WHERE session_id=%d ORDER BY position,id",
            $sessionId
        ), ARRAY_A) ?: [];
        $total = 0;
        foreach ($parts as &$part) {
            $part['exercises'] = $wpdb->get_results($wpdb->prepare(
                "SELECT items.*,exercises.name,exercises.description
                 FROM {$itemsTable} items INNER JOIN {$exercises} exercises ON exercises.id=items.exercise_id
                 WHERE items.part_id=%d ORDER BY items.position,items.id",
                (int) $part['id']
            ), ARRAY_A) ?: [];
            $part['duration'] = array_sum(array_map(static fn(array $item): int => (int) ($item['duration'] ?? 0), $part['exercises']));
            $total += (int) $part['duration'];
        }
        unset($part);

        $library = $wpdb->get_results($wpdb->prepare(
            "SELECT exercises.id,exercises.name,skills.name skill_name,domains.name domain_name
             FROM {$exercises} exercises
             INNER JOIN {$skills} skills ON skills.id=exercises.skill_id
             INNER JOIN {$domains} domains ON domains.id=skills.domain_id
             WHERE domains.category_id=%d AND domains.is_active=1 AND skills.is_active=1
             ORDER BY domains.sort_order,domains.name,skills.sort_order,skills.name,exercises.name",
            (int) $session['category_id']
        ), ARRAY_A) ?: [];

        return ['session' => $session, 'parts' => $parts, 'library' => $library, 'duration' => $total];
    }

    public function updateGeneral(int $groupId, string $date, int $sessionId, string $name, string $objectives): bool
    {
        global $wpdb;
        $session = $this->session($sessionId);
        if ($session === null || !$this->isEditableContext($groupId, $date, $sessionId) || trim($name) === '') {
            return false;
        }
        $duplicate = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Config::table('sessions') . ' WHERE category_id=%d AND name=%s AND id<>%d',
            (int) $session['category_id'], trim($name), $sessionId
        ));
        if ($duplicate > 0) {
            return false;
        }
        return $wpdb->update(Config::table('sessions'), [
            'name' => trim($name), 'objectives' => $objectives, 'updated_at' => current_time('mysql'),
        ], ['id' => $sessionId], ['%s', '%s', '%s'], ['%d']) !== false;
    }

    public function createPart(int $groupId, string $date, int $sessionId, string $title): bool
    {
        global $wpdb;
        if (!$this->isEditableContext($groupId, $date, $sessionId) || trim($title) === '') return false;
        $table = Config::table('session_parts');
        $position = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(position),0)+1 FROM {$table} WHERE session_id=%d", $sessionId));
        return $wpdb->insert($table, ['session_id'=>$sessionId,'title'=>trim($title),'position'=>$position,'created_at'=>current_time('mysql')], ['%d','%s','%d','%s']) !== false;
    }

    public function updatePart(int $groupId, string $date, int $sessionId, int $partId, string $title): bool
    {
        global $wpdb;
        if (!$this->partBelongs($groupId, $date, $sessionId, $partId) || trim($title) === '') return false;
        return $wpdb->update(Config::table('session_parts'), ['title'=>trim($title),'updated_at'=>current_time('mysql')], ['id'=>$partId], ['%s','%s'], ['%d']) !== false;
    }

    public function movePart(int $groupId, string $date, int $sessionId, int $partId, string $direction): bool
    {
        if (!$this->partBelongs($groupId, $date, $sessionId, $partId) || !in_array($direction, ['up','down'], true)) return false;
        return $this->swapPosition(Config::table('session_parts'), $partId, 'session_id', $direction);
    }

    public function deletePart(int $groupId, string $date, int $sessionId, int $partId): bool
    {
        global $wpdb;
        if (!$this->partBelongs($groupId, $date, $sessionId, $partId)) return false;
        $wpdb->query('START TRANSACTION');
        if ($wpdb->delete(Config::table('session_exercises'), ['part_id'=>$partId], ['%d']) === false
            || $wpdb->delete(Config::table('session_parts'), ['id'=>$partId], ['%d']) === false) {
            $wpdb->query('ROLLBACK'); return false;
        }
        $wpdb->query('COMMIT'); return true;
    }

    public function createExercise(int $groupId, string $date, int $sessionId, int $partId, int $exerciseId, int $duration, string $notes): bool
    {
        global $wpdb;
        if (!$this->partBelongs($groupId, $date, $sessionId, $partId) || $duration <= 0 || !$this->exerciseAllowed($sessionId, $exerciseId)) return false;
        $table = Config::table('session_exercises');
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE part_id=%d AND exercise_id=%d",$partId,$exerciseId))>0) return false;
        $position=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(position),0)+1 FROM {$table} WHERE part_id=%d",$partId));
        return $wpdb->insert($table,['part_id'=>$partId,'exercise_id'=>$exerciseId,'position'=>$position,'duration'=>$duration,'coach_notes'=>$notes,'created_at'=>current_time('mysql')],['%d','%d','%d','%d','%s','%s'])!==false;
    }

    public function updateExercise(int $groupId,string $date,int $sessionId,int $itemId,int $duration,string $notes):bool
    {
        global $wpdb;
        if(!$this->itemBelongs($groupId,$date,$sessionId,$itemId)||$duration<=0)return false;
        return $wpdb->update(Config::table('session_exercises'),['duration'=>$duration,'coach_notes'=>$notes,'updated_at'=>current_time('mysql')],['id'=>$itemId],['%d','%s','%s'],['%d'])!==false;
    }

    public function moveExercise(int $groupId,string $date,int $sessionId,int $itemId,string $direction):bool
    {
        if(!$this->itemBelongs($groupId,$date,$sessionId,$itemId)||!in_array($direction,['up','down'],true))return false;
        return $this->swapPosition(Config::table('session_exercises'),$itemId,'part_id',$direction);
    }

    public function deleteExercise(int $groupId,string $date,int $sessionId,int $itemId):bool
    {
        global $wpdb;
        return $this->itemBelongs($groupId,$date,$sessionId,$itemId)
            && $wpdb->delete(Config::table('session_exercises'),['id'=>$itemId],['%d'])!==false;
    }

    public function isEditableContext(int $groupId,string $date,int $sessionId):bool
    {
        global $wpdb;
        if(!$this->validDate($date))return false;
        return (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM '.Config::table('scheduled_sessions').' WHERE group_id=%d AND session_date=%s AND session_id=%d AND coach_editable_copy=1',
            $groupId,$date,$sessionId
        ))>0;
    }

    private function group(int $id):?array {global $wpdb;$g=Config::table('groups');$s=Config::table('seasons');$r=$wpdb->get_row($wpdb->prepare("SELECT g.* FROM {$g} g INNER JOIN {$s} s ON s.id=g.season_id WHERE g.id=%d AND g.is_active=1 AND s.is_active=1",$id),ARRAY_A);return is_array($r)?$r:null;}
    private function session(int $id):?array {global $wpdb;$r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Config::table('sessions').' WHERE id=%d',$id),ARRAY_A);return is_array($r)?$r:null;}
    private function partBelongs(int $gid,string $date,int $sid,int $pid):bool {global $wpdb;return $this->isEditableContext($gid,$date,$sid)&&(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('session_parts').' WHERE id=%d AND session_id=%d',$pid,$sid))>0;}
    private function itemBelongs(int $gid,string $date,int $sid,int $iid):bool {global $wpdb;$p=Config::table('session_parts');$i=Config::table('session_exercises');return $this->isEditableContext($gid,$date,$sid)&&(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$i} i INNER JOIN {$p} p ON p.id=i.part_id WHERE i.id=%d AND p.session_id=%d",$iid,$sid))>0;}
    private function exerciseAllowed(int $sid,int $eid):bool {global $wpdb;$s=Config::table('sessions');$e=Config::table('exercises');$k=Config::table('skills');$d=Config::table('skill_domains');return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$s} s INNER JOIN {$d} d ON d.category_id=s.category_id INNER JOIN {$k} k ON k.domain_id=d.id INNER JOIN {$e} e ON e.skill_id=k.id WHERE s.id=%d AND e.id=%d AND d.is_active=1 AND k.is_active=1",$sid,$eid))>0;}
    private function uniqueName(int $categoryId,string $name):string {global $wpdb;$name=trim($name);if($name==='')return '';$base=$name;$suffix=2;while((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('sessions').' WHERE category_id=%d AND name=%s',$categoryId,$name))>0){$name=sprintf('%s (%d)',$base,$suffix++);}return $name;}
    private function validDate(string $date):bool {$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());return $parsed instanceof \DateTimeImmutable&&$parsed->format('Y-m-d')===$date;}

    private function copyContents(int $sourceId,int $targetId,string $parts,string $items,string $now):bool
    {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$parts} WHERE session_id=%d ORDER BY position,id",$sourceId),ARRAY_A)?:[];
        foreach($rows as $part){if($wpdb->insert($parts,['session_id'=>$targetId,'title'=>$part['title'],'position'=>(int)$part['position'],'created_at'=>$now],['%d','%s','%d','%s'])===false)return false;$newPart=(int)$wpdb->insert_id;$exerciseRows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$items} WHERE part_id=%d ORDER BY position,id",(int)$part['id']),ARRAY_A)?:[];foreach($exerciseRows as $item){if($wpdb->insert($items,['part_id'=>$newPart,'exercise_id'=>(int)$item['exercise_id'],'position'=>(int)$item['position'],'duration'=>$item['duration'],'coach_notes'=>(string)$item['coach_notes'],'created_at'=>$now],['%d','%d','%d','%d','%s','%s'])===false)return false;}}
        return true;
    }

    private function swapPosition(string $table,int $id,string $parentColumn,string $direction):bool
    {
        global $wpdb;$current=$wpdb->get_row($wpdb->prepare("SELECT id,{$parentColumn},position FROM {$table} WHERE id=%d",$id),ARRAY_A);if(!is_array($current))return false;$op=$direction==='up'?'<':'>';$order=$direction==='up'?'DESC':'ASC';$neighbour=$wpdb->get_row($wpdb->prepare("SELECT id,position FROM {$table} WHERE {$parentColumn}=%d AND position {$op} %d ORDER BY position {$order},id {$order} LIMIT 1",(int)$current[$parentColumn],(int)$current['position']),ARRAY_A);if(!is_array($neighbour))return true;$wpdb->query('START TRANSACTION');$a=$wpdb->update($table,['position'=>(int)$neighbour['position'],'updated_at'=>current_time('mysql')],['id'=>$id],['%d','%s'],['%d']);$b=$wpdb->update($table,['position'=>(int)$current['position'],'updated_at'=>current_time('mysql')],['id'=>(int)$neighbour['id']],['%d','%s'],['%d']);if($a===false||$b===false){$wpdb->query('ROLLBACK');return false;}$wpdb->query('COMMIT');return true;
    }
}
