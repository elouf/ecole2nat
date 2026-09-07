<?php
namespace Ecole2Nat\Distribution;
if (!defined('ABSPATH')) { exit; }
final class DistributionService
{
    public function __construct(private ?DistributionRepository $repository=null){$this->repository??=new DistributionRepository();}
    public function adminList():array{return $this->repository->adminList();}
    public function find(int $id):?array{return $this->repository->find($id);}
    public function selectableSwimmers():array{return $this->repository->selectableSwimmers();}
    public function targetIds(int $id):array{return $this->repository->targetIds($id);}
    public function save(int $id,string $name,string $start,string $end,array $ids,int $userId):array
    {
        $name=trim($name); if($name===''||!$this->validDate($start)||!$this->validDate($end)||$end<$start)return ['success'=>false,'message'=>__('Renseignez un nom et une période valide.','ecole2nat')];
        $allowed=array_column($this->selectableSwimmers(),'id'); $ids=array_values(array_unique(array_intersect(array_map('intval',$ids),array_map('intval',$allowed))));
        if($ids===[])return ['success'=>false,'message'=>__('Sélectionnez au moins un nageur.','ecole2nat')];
        $now=current_time('mysql');$data=['name'=>$name,'start_date'=>$start,'end_date'=>$end,'updated_at'=>$now];if($id===0){$data['created_by']=$userId;$data['created_at']=$now;}
        $saved=$this->repository->save($id,$data,$ids);return ['success'=>$saved>0,'id'=>$saved,'message'=>$saved>0?'':__('Impossible d’enregistrer la distribution.','ecole2nat')];
    }
    public function coachList():array{return $this->repository->coachList();}
    public function coachDetail(int $id):?array{return $this->repository->coachDetail($id);}
    public function toggle(int $distributionId,int $swimmerId,bool $delivered,int $userId):array
    { if(!$this->repository->setDelivered($distributionId,$swimmerId,$delivered,$userId))return ['success'=>false,'message'=>__('Modification non enregistrée.','ecole2nat')];return ['success'=>true,'delivered'=>$delivered,'label'=>$delivered?sprintf(__('Distribué le %1$s par %2$s','ecole2nat'),wp_date('d/m/Y'),wp_get_current_user()->display_name):__('Non distribué','ecole2nat')]; }
    public function deliveredForSwimmer(int $id):array{return $this->repository->deliveredForSwimmer($id);}
    private function validDate(string $date):bool{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);return $d!==false&&$d->format('Y-m-d')===$date;}
}
