<?php
namespace Ecole2Nat\Coach;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\GroupScheduleParser;
if(!defined('ABSPATH')){exit;}
class CoachPortalRepository {
 public function groups():array {
  global $wpdb;
  $g=Config::table('groups');$s=Config::table('seasons');$c=Config::table('categories');
  $rows=$wpdb->get_results("SELECT g.*,c.name category_name,s.name season_name,s.is_current
      FROM {$g} g
      INNER JOIN {$s} s ON s.id=g.season_id
      INNER JOIN {$c} c ON c.id=g.category_id
      WHERE g.is_active=1 AND s.is_active=1
      ORDER BY s.is_current DESC,s.start_date DESC,g.weekday,g.start_time,g.name",ARRAY_A);
  if (!is_array($rows)) return [];
  foreach ($rows as &$row) { $this->enrichSchedule($row); }
  unset($row);
  usort($rows, static function(array $a,array $b):int {
   $aw=(int)($a['weekday']??99); $bw=(int)($b['weekday']??99);
   if($aw!==$bw) return $aw<=>$bw;
   $at=(string)($a['start_time']??'99:99:99'); $bt=(string)($b['start_time']??'99:99:99');
   if($at!==$bt) return strcmp($at,$bt);
   $seasonOrder=(int)($b['is_current']??0)<=>(int)($a['is_current']??0);
   return $seasonOrder!==0 ? $seasonOrder : strcasecmp((string)$a['name'],(string)$b['name']);
  });
  return $rows;
 }
 public function group(int $id):?array {global $wpdb;$g=Config::table('groups');$s=Config::table('seasons');$c=Config::table('categories');$r=$wpdb->get_row($wpdb->prepare("SELECT g.*,c.name category_name,s.name season_name FROM {$g} g INNER JOIN {$s} s ON s.id=g.season_id INNER JOIN {$c} c ON c.id=g.category_id WHERE g.id=%d AND g.is_active=1 AND s.is_active=1",$id),ARRAY_A);if(!is_array($r))return null;$this->enrichSchedule($r);return $r;}
 public function swimmers(int $groupId,int $seasonId):array {global $wpdb;$m=Config::table('swimmer_group_memberships');$sw=Config::table('swimmers');return $wpdb->get_results($wpdb->prepare("SELECT s.id,s.first_name,s.last_name,s.birth_date,s.medical_note,s.image_rights,s.parent_message FROM {$m} m INNER JOIN {$sw} s ON s.id=m.swimmer_id WHERE m.group_id=%d AND m.season_id=%d AND s.is_active=1 ORDER BY s.last_name,s.first_name",$groupId,$seasonId),ARRAY_A)?:[];}
 public function sessionsForCategory(int $categoryId):array {global $wpdb;$t=Config::table('sessions');return $wpdb->get_results($wpdb->prepare("SELECT id,name,objectives FROM {$t} WHERE category_id=%d AND is_active=1 ORDER BY name",$categoryId),ARRAY_A)?:[];}
 public function planned(int $groupId,string $date):?array {global $wpdb;$p=Config::table('scheduled_sessions');$s=Config::table('sessions');$r=$wpdb->get_row($wpdb->prepare("SELECT p.*,s.name session_name,s.objectives FROM {$p} p INNER JOIN {$s} s ON s.id=p.session_id WHERE p.group_id=%d AND p.session_date=%s",$groupId,$date),ARRAY_A);return is_array($r)?$r:null;}
 public function schedule(int $groupId,int $sessionId,string $date,int $userId):bool {global $wpdb;$g=$this->group($groupId);if($g===null||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return false;$sessions=$this->sessionsForCategory((int)$g['category_id']);$allowed=array_map(fn($row)=>(int)$row['id'],$sessions);if(!in_array($sessionId,$allowed,true))return false;$t=Config::table('scheduled_sessions');$now=current_time('mysql');$sql="INSERT INTO {$t}(group_id,session_id,session_date,status,created_by,created_at,updated_at) VALUES(%d,%d,%s,'planned',%d,%s,%s) ON DUPLICATE KEY UPDATE session_id=VALUES(session_id),status='planned',completed_by=NULL,completed_at=NULL,created_by=VALUES(created_by),updated_at=VALUES(updated_at)";return $wpdb->query($wpdb->prepare($sql,$groupId,$sessionId,$date,$userId,$now,$now))!==false;}
 private function enrichSchedule(array &$group):void {
  if (!empty($group['weekday']) && !empty($group['start_time'])) return;
  $parsed=GroupScheduleParser::parse((string)($group['name']??''));
  if (empty($group['weekday']) && $parsed['weekday']!==null) $group['weekday']=$parsed['weekday'];
  if (empty($group['start_time']) && $parsed['start_time']!==null) $group['start_time']=$parsed['start_time'];
 }

 public function sessionDetail(int $id):?array {global $wpdb;$s=Config::table('sessions');$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$s} WHERE id=%d AND is_active=1",$id),ARRAY_A);if(!is_array($r))return null;$parts=Config::table('session_parts');$se=Config::table('session_exercises');$ex=Config::table('exercises');$r['parts']=$wpdb->get_results($wpdb->prepare("SELECT p.id,p.title,p.position FROM {$parts} p WHERE p.session_id=%d ORDER BY p.position,p.id",$id),ARRAY_A)?:[];foreach($r['parts'] as &$p){$p['exercises']=$wpdb->get_results($wpdb->prepare("SELECT se.duration,se.coach_notes,e.name,e.description,e.objectives,e.equipment FROM {$se} se INNER JOIN {$ex} e ON e.id=se.exercise_id WHERE se.part_id=%d ORDER BY se.position,se.id",$p['id']),ARRAY_A)?:[];}return $r;}
}
