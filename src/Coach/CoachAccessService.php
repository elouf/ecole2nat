<?php
namespace Ecole2Nat\Coach;
if(!defined('ABSPATH')){exit;}
class CoachAccessService {
 private CoachAccessRepository $repo; public function __construct(){ $this->repo=new CoachAccessRepository(); }
 public function canView():bool { return current_user_can('manage_options') || current_user_can('e2n_coach_access'); }
 public function canEditGroup(int $groupId):bool { return current_user_can('manage_options') || ($this->canView() && $this->repo->isTitular(get_current_user_id(),$groupId)); }
 public function titularGroupIds(?int $uid=null):array{return $this->repo->titularGroupIds($uid??get_current_user_id());}
 public function saveAssignments(int $uid,array $ids):bool{return $this->repo->replaceAssignments($uid,$ids);}
}
