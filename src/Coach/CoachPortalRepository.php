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
 public function swimmers(int $groupId,int $seasonId):array {global $wpdb;$m=Config::table('swimmer_group_memberships');$sw=Config::table('swimmers');return $wpdb->get_results($wpdb->prepare("SELECT s.id,s.first_name,s.last_name,s.birth_date,s.health_alert,s.image_rights,s.parent_message FROM {$m} m INNER JOIN {$sw} s ON s.id=m.swimmer_id WHERE m.group_id=%d AND m.season_id=%d AND s.is_active=1 ORDER BY s.last_name,s.first_name",$groupId,$seasonId),ARRAY_A)?:[];}
 public function allSwimmers():array {
  global $wpdb;
  $m=Config::table('swimmer_group_memberships');$sw=Config::table('swimmers');$g=Config::table('groups');$s=Config::table('seasons');$c=Config::table('categories');
  $rows=$wpdb->get_results("SELECT sw.id,sw.first_name,sw.last_name,sw.licence_number,sw.health_alert,sw.image_rights,
      grp.id group_id,grp.name group_name,cat.id category_id,cat.name category_name,
      sea.id season_id,sea.name season_name,sea.is_current
      FROM {$m} membership
      INNER JOIN {$sw} sw ON sw.id=membership.swimmer_id
      INNER JOIN {$g} grp ON grp.id=membership.group_id
      INNER JOIN {$s} sea ON sea.id=membership.season_id
      INNER JOIN {$c} cat ON cat.id=grp.category_id
      WHERE sw.is_active=1 AND grp.is_active=1 AND sea.is_active=1
      ORDER BY sw.last_name,sw.first_name,sea.is_current DESC,sea.start_date DESC,grp.name",ARRAY_A);
  if(!is_array($rows)) return [];
  $unique=[];
  foreach($rows as $row){$id=(int)$row['id'];if(!isset($unique[$id]))$unique[$id]=$row;}
  return array_values($unique);
 }
 public function titularNames(int $groupId):array {global $wpdb;$gc=Config::table('group_coaches');$rows=$wpdb->get_col($wpdb->prepare("SELECT users.display_name FROM {$gc} assignments INNER JOIN {$wpdb->users} users ON users.ID=assignments.user_id WHERE assignments.group_id=%d ORDER BY users.display_name",$groupId));return is_array($rows)?array_values(array_map('strval',$rows)):[];}
 private function enrichSchedule(array &$group):void {
  if (!empty($group['weekday']) && !empty($group['start_time'])) return;
  $parsed=GroupScheduleParser::parse((string)($group['name']??''));
  if (empty($group['weekday']) && $parsed['weekday']!==null) $group['weekday']=$parsed['weekday'];
  if (empty($group['start_time']) && $parsed['start_time']!==null) $group['start_time']=$parsed['start_time'];
 }
}
