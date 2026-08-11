<?php
namespace Ecole2Nat\Evaluation;
if(!defined('ABSPATH')){exit;}
class EvaluationService
{
    public const STATUS_NOT_OBSERVED='not_observed'; public const STATUS_IN_PROGRESS='in_progress'; public const STATUS_ACQUIRED='acquired';
    private EvaluationRepository $repository;
    public function __construct(){ $this->repository=new EvaluationRepository(); }
    public function groups():array{return $this->repository->groups();}
    public function groupContext(int $groupId):?array
    {
        $group=$this->repository->findGroup($groupId); if($group===null)return null; $seasonId=(int)$group['season_id'];
        $skills=$this->repository->skillsByCategory((int)$group['category_id'],$seasonId);
        $swimmers=$this->repository->swimmersByGroup($groupId,$seasonId); $count=count($skills);
        foreach($swimmers as &$sw){$in=(int)($sw['in_progress_count']??0);$ac=(int)($sw['acquired_count']??0);$sw['not_observed_count']=max(0,$count-$in-$ac);$sw['skill_count']=$count;} unset($sw);
        return ['group'=>$group,'skills'=>$skills,'swimmers'=>$swimmers];
    }
    public function swimmerEvaluation(int $groupId,int $swimmerId):?array
    {
        $ctx=$this->groupContext($groupId); if($ctx===null)return null; $seasonId=(int)$ctx['group']['season_id'];
        $sw=$this->repository->findSwimmerInGroup($swimmerId,$groupId,$seasonId); if($sw===null)return null;
        $levels=$this->repository->levelsBySwimmer($swimmerId,$seasonId);
        foreach($ctx['skills'] as &$skill){$id=(int)$skill['id'];$saved=$levels[$id]??null;$skill['status']=is_array($saved)?(string)$saved['status']:self::STATUS_NOT_OBSERVED;$skill['notes']=is_array($saved)?(string)($saved['notes']??''):'';$skill['evaluated_at']=is_array($saved)?($saved['evaluated_at']??null):null;$skill['evaluator_name']=is_array($saved)?(string)($saved['evaluator_name']??''):'';} unset($skill);
        return ['group'=>$ctx['group'],'swimmer'=>$sw,'skills'=>$ctx['skills']];
    }
    public function save(int $groupId,int $swimmerId,array $statuses,array $notes,int $userId):array
    {
        $eval=$this->swimmerEvaluation($groupId,$swimmerId); if($eval===null)return ['success'=>false,'message'=>'invalid']; $allowed=$this->statuses();$levels=[];
        foreach($eval['skills'] as $skill){$id=(int)$skill['id'];$status=isset($statuses[$id])?sanitize_key((string)$statuses[$id]):self::STATUS_NOT_OBSERVED;if(!isset($allowed[$status]))$status=self::STATUS_NOT_OBSERVED;$levels[$id]=['status'=>$status,'notes'=>isset($notes[$id])?sanitize_textarea_field((string)$notes[$id]):''];}
        $ok=$this->repository->saveLevels($swimmerId,(int)$eval['group']['season_id'],$levels,$userId); return ['success'=>$ok,'message'=>$ok?'levels_saved':'error'];
    }
    public function statuses():array{return [self::STATUS_NOT_OBSERVED=>__('Non observé','ecole2nat'),self::STATUS_IN_PROGRESS=>__('En cours','ecole2nat'),self::STATUS_ACQUIRED=>__('Acquis','ecole2nat')];}
}
