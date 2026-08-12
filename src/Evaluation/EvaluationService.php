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
    public function collectiveEvaluation(int $groupId,int $skillId):?array
    {
        $ctx=$this->groupContext($groupId); if($ctx===null)return null;
        $skill=null;
        foreach($ctx['skills'] as $candidate){if((int)$candidate['id']===$skillId){$skill=$candidate;break;}}
        if($skill===null)return null;
        $ids=array_map(static fn(array $sw):int=>(int)$sw['id'],$ctx['swimmers']);
        $levels=$this->repository->levelsBySkillForSwimmers($skillId,(int)$ctx['group']['season_id'],$ids);
        foreach($ctx['swimmers'] as &$sw){$saved=$levels[(int)$sw['id']]??null;$sw['status']=is_array($saved)?(string)$saved['status']:self::STATUS_NOT_OBSERVED;}unset($sw);
        return ['group'=>$ctx['group'],'skill'=>$skill,'swimmers'=>$ctx['swimmers']];
    }

    public function saveCollective(int $groupId,int $skillId,array $statuses,int $userId):array
    {
        $data=$this->collectiveEvaluation($groupId,$skillId); if($data===null)return ['success'=>false,'message'=>'invalid'];
        $allowed=$this->statuses();$memberIds=array_map(static fn(array $sw):int=>(int)$sw['id'],$data['swimmers']);$clean=[];
        foreach($statuses as $swimmerId=>$status){$swimmerId=(int)$swimmerId;$status=sanitize_key((string)$status);if(in_array($swimmerId,$memberIds,true)&&isset($allowed[$status]))$clean[$swimmerId]=$status;}
        $ok=$this->repository->saveCollectiveSkill((int)$data['group']['season_id'],$skillId,$clean,$userId);
        return ['success'=>$ok,'message'=>$ok?'collective_saved':'error'];
    }


    public function saveSingleStatus(int $groupId,int $swimmerId,int $skillId,string $status,int $userId):array
    {
        $data=$this->swimmerEvaluation($groupId,$swimmerId);
        if($data===null)return ['success'=>false,'message'=>'invalid'];
        $allowed=$this->statuses();
        if(!isset($allowed[$status]))return ['success'=>false,'message'=>'invalid'];
        $current=null;
        foreach($data['skills'] as $skill){if((int)$skill['id']===$skillId){$current=$skill;break;}}
        if($current===null)return ['success'=>false,'message'=>'invalid'];
        $ok=$this->repository->saveStatusOnly($swimmerId,(int)$data['group']['season_id'],$skillId,$status,$userId);
        return ['success'=>$ok,'message'=>$ok?'level_saved':'error'];
    }

    public function saveSingleNote(int $groupId,int $swimmerId,int $skillId,string $note,int $userId):array
    {
        $data=$this->swimmerEvaluation($groupId,$swimmerId);
        if($data===null)return ['success'=>false,'message'=>'invalid'];
        $current=null;
        foreach($data['skills'] as $skill){if((int)$skill['id']===$skillId){$current=$skill;break;}}
        if($current===null)return ['success'=>false,'message'=>'invalid'];
        $ok=$this->repository->saveNoteOnly($swimmerId,(int)$data['group']['season_id'],$skillId,$note,$userId);
        return ['success'=>$ok,'message'=>$ok?'note_saved':'error'];
    }

    public function statuses():array{return [self::STATUS_NOT_OBSERVED=>__('Non observé','ecole2nat'),self::STATUS_IN_PROGRESS=>__('En cours','ecole2nat'),self::STATUS_ACQUIRED=>__('Acquis','ecole2nat')];}
}
