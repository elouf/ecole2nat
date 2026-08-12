<?php
namespace Ecole2Nat\Coach;
use Ecole2Nat\Support\Config;
if(!defined('ABSPATH')){exit;}
class CoachAccessRepository {
 public function isTitular(int $userId,int $groupId):bool { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('group_coaches').' WHERE user_id=%d AND group_id=%d',$userId,$groupId))>0; }
 public function titularGroupIds(int $userId):array { global $wpdb; return array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT group_id FROM '.Config::table('group_coaches').' WHERE user_id=%d',$userId))); }
 public function replaceAssignments(int $userId,array $groupIds):bool { global $wpdb;$t=Config::table('group_coaches');$wpdb->query('START TRANSACTION'); if($wpdb->delete($t,['user_id'=>$userId],['%d'])===false){$wpdb->query('ROLLBACK');return false;} foreach(array_unique(array_map('intval',$groupIds)) as $gid){if($gid<=0)continue;if($wpdb->insert($t,['group_id'=>$gid,'user_id'=>$userId,'created_at'=>current_time('mysql')],['%d','%d','%s'])===false){$wpdb->query('ROLLBACK');return false;}} $wpdb->query('COMMIT');return true; }
 public function allAssignments():array { global $wpdb;$t=Config::table('group_coaches');$g=Config::table('groups'); return $wpdb->get_results("SELECT gc.user_id,g.id group_id,g.name group_name FROM {$t} gc INNER JOIN {$g} g ON g.id=gc.group_id ORDER BY g.name",ARRAY_A)?:[]; }
}
