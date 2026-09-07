<?php

namespace Ecole2Nat\Distribution;

use Ecole2Nat\Support\Config;

if (!defined('ABSPATH')) { exit; }

final class DistributionRepository
{
    public function adminList(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT d.*,COUNT(DISTINCT t.swimmer_id) target_count,COUNT(DISTINCT x.swimmer_id) delivered_count FROM '.Config::table('distributions').' d LEFT JOIN '.Config::table('distribution_targets').' t ON t.distribution_id=d.id LEFT JOIN '.Config::table('distribution_deliveries').' x ON x.distribution_id=d.id GROUP BY d.id ORDER BY d.start_date DESC,d.name', ARRAY_A) ?: [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Config::table('distributions').' WHERE id=%d',$id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function selectableSwimmers(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT s.id,s.first_name,s.last_name,g.id group_id,g.name group_name,c.id category_id,c.name category_name FROM '.Config::table('swimmers').' s INNER JOIN '.Config::table('groups').' g ON g.id=s.group_id AND g.is_active=1 INNER JOIN '.Config::table('seasons').' se ON se.id=g.season_id AND se.is_active=1 LEFT JOIN '.Config::table('categories').' c ON c.id=g.category_id WHERE s.is_active=1 ORDER BY s.last_name,s.first_name',ARRAY_A)?:[];
    }

    public function targetIds(int $id): array
    { global $wpdb; return array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT swimmer_id FROM '.Config::table('distribution_targets').' WHERE distribution_id=%d',$id))?:[]); }

    public function save(int $id, array $data, array $swimmerIds): int
    {
        global $wpdb; $wpdb->query('START TRANSACTION');
        try {
            if($id>0){ if($wpdb->update(Config::table('distributions'),$data,['id'=>$id])===false) throw new \RuntimeException('update'); }
            else { if(!$wpdb->insert(Config::table('distributions'),$data)) throw new \RuntimeException('insert'); $id=(int)$wpdb->insert_id; }
            $existing=$this->targetIds($id); $removed=array_diff($existing,$swimmerIds);
            foreach($removed as $swimmerId){
                if($wpdb->delete(Config::table('distribution_deliveries'),['distribution_id'=>$id,'swimmer_id'=>$swimmerId],['%d','%d'])===false) throw new \RuntimeException('delivery');
                if($wpdb->delete(Config::table('distribution_targets'),['distribution_id'=>$id,'swimmer_id'=>$swimmerId],['%d','%d'])===false) throw new \RuntimeException('target');
            }
            foreach(array_diff($swimmerIds,$existing) as $swimmerId){ if(!$wpdb->insert(Config::table('distribution_targets'),['distribution_id'=>$id,'swimmer_id'=>$swimmerId,'created_at'=>current_time('mysql')],['%d','%d','%s'])) throw new \RuntimeException('target'); }
            $wpdb->query('COMMIT'); return $id;
        } catch(\Throwable $e){ $wpdb->query('ROLLBACK'); return 0; }
    }

    public function coachList(): array { return $this->adminList(); }

    public function coachDetail(int $id): ?array
    {
        global $wpdb; $distribution=$this->find($id); if($distribution===null)return null;
        $distribution['swimmers']=$wpdb->get_results($wpdb->prepare('SELECT s.id,s.first_name,s.last_name,g.name group_name,c.id category_id,c.name category_name,x.delivered_at,x.delivered_by,u.display_name delivered_by_name FROM '.Config::table('distribution_targets').' t INNER JOIN '.Config::table('swimmers').' s ON s.id=t.swimmer_id LEFT JOIN '.Config::table('groups').' g ON g.id=s.group_id LEFT JOIN '.Config::table('categories').' c ON c.id=g.category_id LEFT JOIN '.Config::table('distribution_deliveries').' x ON x.distribution_id=t.distribution_id AND x.swimmer_id=t.swimmer_id LEFT JOIN '.$wpdb->users.' u ON u.ID=x.delivered_by WHERE t.distribution_id=%d ORDER BY COALESCE(c.name,%s),s.last_name,s.first_name',$id,__('Sans catégorie','ecole2nat')),ARRAY_A)?:[];
        return $distribution;
    }

    public function setDelivered(int $distributionId,int $swimmerId,bool $delivered,int $userId): bool
    {
        global $wpdb; $target=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Config::table('distribution_targets').' WHERE distribution_id=%d AND swimmer_id=%d',$distributionId,$swimmerId)); if($target!==1)return false;
        if(!$delivered)return $wpdb->delete(Config::table('distribution_deliveries'),['distribution_id'=>$distributionId,'swimmer_id'=>$swimmerId],['%d','%d'])!==false;
        $now=current_time('mysql'); return $wpdb->replace(Config::table('distribution_deliveries'),['distribution_id'=>$distributionId,'swimmer_id'=>$swimmerId,'delivered_at'=>$now,'delivered_by'=>$userId,'created_at'=>$now],['%d','%d','%s','%d','%s'])!==false;
    }

    public function deliveredForSwimmer(int $swimmerId): array
    {
        global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT d.name,x.delivered_at,COALESCE(u.display_name,%s) coach_name FROM '.Config::table('distribution_deliveries').' x INNER JOIN '.Config::table('distributions').' d ON d.id=x.distribution_id LEFT JOIN '.$wpdb->users.' u ON u.ID=x.delivered_by WHERE x.swimmer_id=%d ORDER BY x.delivered_at DESC',__('un coach','ecole2nat'),$swimmerId),ARRAY_A)?:[];
    }
}
