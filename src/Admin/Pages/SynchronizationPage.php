<?php

namespace Ecole2Nat\Admin\Pages;

use Ecole2Nat\Synchronization\SynchronizationService;
use Ecole2Nat\Season\SeasonRepository;

if (!defined('ABSPATH')) { exit; }

final class SynchronizationPage
{
    private SynchronizationService $service;
    private SeasonRepository $seasonRepository;
    public function __construct(){ $this->service=new SynchronizationService(); $this->seasonRepository=new SeasonRepository(); }

    public function render():void
    {
        if(!current_user_can('manage_options')) wp_die(esc_html__('Vous n’avez pas les droits nécessaires.','ecole2nat'));
        $this->handleActions();
        $state=get_transient($this->stateKey());
        $seasons=$this->seasonRepository->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Synchronisation du classeur club','ecole2nat'); ?></h1>
            <?php $this->renderNotice(); ?>
            <div class="postbox"><div class="postbox-header"><h2 class="hndle"><?php esc_html_e('1. Analyser le classeur','ecole2nat'); ?></h2></div><div class="inside">
                <p><?php esc_html_e('Déposez le classeur de travail du club au format .xlsx. Les onglets comptables et les colonnes non reconnues seront ignorés.','ecole2nat'); ?></p>
                <?php if($seasons===[]): ?>
                    <div class="notice notice-warning inline"><p><?php esc_html_e('Créez d’abord une saison avant de synchroniser un classeur.','ecole2nat'); ?></p></div>
                <?php else: ?>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('e2n_sync_analyze'); ?>
                    <input type="hidden" name="e2n_action" value="analyze_workbook">
                    <p><label for="e2n-sync-season"><strong><?php esc_html_e('Saison cible','ecole2nat'); ?></strong></label><br>
                    <select id="e2n-sync-season" name="season_id" required>
                        <option value=""><?php esc_html_e('— Sélectionner —','ecole2nat'); ?></option>
                        <?php foreach($seasons as $season): ?>
                            <option value="<?php echo esc_attr((string)$season['id']); ?>" <?php selected((int)($season['is_current']??0),1); ?>><?php echo esc_html($season['name']); ?></option>
                        <?php endforeach; ?>
                    </select></p>
                    <p><input type="file" name="club_workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></p>
                    <?php submit_button(__('Analyser uniquement','ecole2nat'),'primary','submit',false); ?>
                </form>
                <?php endif; ?>
            </div></div>
            <?php if(is_array($state) && isset($state['analysis'])): $this->renderAnalysis($state); endif; ?>
            <?php $this->renderLogs(); ?>
        </div>
        <?php
    }

    private function handleActions():void
    {
        if($_SERVER['REQUEST_METHOD']!=='POST') return;
        $action=isset($_POST['e2n_action'])?sanitize_key(wp_unslash($_POST['e2n_action'])):'';
        if($action==='analyze_workbook'){
            check_admin_referer('e2n_sync_analyze');
            $seasonId=isset($_POST['season_id'])?absint($_POST['season_id']):0;
            $season=$this->findSeason($seasonId);
            if($season===null) $this->redirect('invalid_season');
            if(empty($_FILES['club_workbook']['tmp_name']) || !is_uploaded_file($_FILES['club_workbook']['tmp_name'])) $this->redirect('upload_error');
            $name=sanitize_file_name((string)$_FILES['club_workbook']['name']);
            if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx') $this->redirect('invalid_file');
            if((int)$_FILES['club_workbook']['size']>20*1024*1024) $this->redirect('file_too_large');
            $uploads=wp_upload_dir(); $dir=trailingslashit($uploads['basedir']).'ecole2nat-sync';
            if(!wp_mkdir_p($dir)) $this->redirect('upload_error');
            $path=$dir.'/'.get_current_user_id().'-'.wp_generate_uuid4().'.xlsx';
            if(!move_uploaded_file($_FILES['club_workbook']['tmp_name'],$path)) $this->redirect('upload_error');
            $analysis=$this->service->analyze($path,$season);
            set_transient($this->stateKey(),['path'=>$path,'filename'=>$name,'season'=>$season,'analysis'=>$analysis],HOUR_IN_SECONDS);
            $this->redirect($analysis['errors']===[]?'analysis_ready':'analysis_errors');
        }
        if($action==='synchronize_workbook'){
            check_admin_referer('e2n_sync_execute');
            $state=get_transient($this->stateKey());
        $seasons=$this->seasonRepository->all();
            if(!is_array($state)||empty($state['path'])||!is_file($state['path'])) $this->redirect('analysis_expired');
            if(empty($state['season']) || !is_array($state['season'])) $this->redirect('invalid_season');
            $result=$this->service->synchronize($state['path'],$state['filename'],get_current_user_id(),$state['season']);
            @unlink($state['path']); delete_transient($this->stateKey());
            set_transient($this->resultKey(),$result,10*MINUTE_IN_SECONDS);
            $this->redirect($result['success']?'sync_success':'sync_error');
        }
        if($action==='cancel_workbook'){
            check_admin_referer('e2n_sync_cancel'); $state=get_transient($this->stateKey());
            if(is_array($state)&&!empty($state['path'])) @unlink($state['path']);
            delete_transient($this->stateKey()); $this->redirect('analysis_cancelled');
        }
    }

    private function renderAnalysis(array $state):void
    {
        $a=$state['analysis']; $counts=$a['counts'];
        ?>
        <div class="postbox"><div class="postbox-header"><h2 class="hndle"><?php esc_html_e('2. Prévisualisation','ecole2nat'); ?></h2></div><div class="inside">
            <p><strong><?php echo esc_html($state['filename']); ?></strong><br><?php esc_html_e('Saison cible :','ecole2nat'); ?> <strong><?php echo esc_html((string)($state['season']['name']??'')); ?></strong></p>
            <table class="widefat striped" style="max-width:760px"><tbody>
                <tr><th><?php esc_html_e('Groupes','ecole2nat'); ?></th><td><?php echo esc_html((string)$counts['groups']); ?></td></tr>
                <tr><th><?php esc_html_e('Lignes du référentiel','ecole2nat'); ?></th><td><?php echo esc_html((string)$counts['reference_rows']); ?></td></tr>
                <tr><th><?php esc_html_e('Exercices repérés','ecole2nat'); ?></th><td><?php echo esc_html((string)$counts['exercises']); ?></td></tr>
                <tr><th><?php esc_html_e('Nageurs','ecole2nat'); ?></th><td><?php echo esc_html((string)$counts['swimmers']); ?></td></tr>
                <tr><th><?php esc_html_e('Compétitions','ecole2nat'); ?></th><td><?php echo esc_html((string)$counts['competitions']); ?></td></tr>
            </tbody></table>
            <?php if($a['warnings']!==[]): ?><div class="notice notice-warning inline"><p><strong><?php esc_html_e('Avertissements','ecole2nat'); ?></strong></p><ul><?php foreach(array_slice($a['warnings'],0,30) as $warning): ?><li><?php echo esc_html($warning); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if($a['errors']!==[]): ?><div class="notice notice-error inline"><p><strong><?php esc_html_e('Erreurs bloquantes','ecole2nat'); ?></strong></p><ul><?php foreach($a['errors'] as $error): ?><li><?php echo esc_html($error); ?></li><?php endforeach; ?></ul></div><?php else: ?>
                <?php if(!empty($a['plan'])): ?><h3><?php esc_html_e('Modifications prévues','ecole2nat'); ?></h3><p><?php echo esc_html($this->summaryText($a['plan'])); ?></p><?php endif; ?>
                <p><?php esc_html_e('Aucune suppression automatique ne sera effectuée. Les données pédagogiques existantes, évaluations et accès parents seront préservés.','ecole2nat'); ?></p>
                <div style="display:flex;gap:8px">
                    <form method="post"><?php wp_nonce_field('e2n_sync_execute'); ?><input type="hidden" name="e2n_action" value="synchronize_workbook"><?php submit_button(__('Synchroniser maintenant','ecole2nat'),'primary','submit',false); ?></form>
                    <form method="post"><?php wp_nonce_field('e2n_sync_cancel'); ?><input type="hidden" name="e2n_action" value="cancel_workbook"><?php submit_button(__('Annuler','ecole2nat'),'secondary','submit',false); ?></form>
                </div>
            <?php endif; ?>
        </div></div>
        <?php
    }

    private function renderLogs():void
    {
        $logs=$this->service->logs();
        ?><div class="postbox"><div class="postbox-header"><h2 class="hndle"><?php esc_html_e('Historique récent','ecole2nat'); ?></h2></div><div class="inside">
        <?php if($logs===[]): ?><p><?php esc_html_e('Aucune synchronisation enregistrée.','ecole2nat'); ?></p><?php else: ?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Date','ecole2nat'); ?></th><th><?php esc_html_e('Fichier','ecole2nat'); ?></th><th><?php esc_html_e('Statut','ecole2nat'); ?></th><th><?php esc_html_e('Résumé','ecole2nat'); ?></th></tr></thead><tbody><?php foreach($logs as $log): $summary=json_decode((string)$log['summary'],true)?:[]; ?><tr><td><?php echo esc_html(wp_date('d/m/Y H:i',strtotime($log['created_at']))); ?></td><td><?php echo esc_html($log['filename']); ?></td><td><?php echo esc_html($log['status']==='success'?'Réussie':'Erreur'); ?></td><td><?php echo esc_html($this->summaryText($summary)); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </div></div><?php
    }

    private function renderNotice():void
    {
        $notice=isset($_GET['e2n_notice'])?sanitize_key(wp_unslash($_GET['e2n_notice'])):'';
        $messages=['analysis_ready'=>['success','Analyse terminée. Vérifiez le rapport avant de synchroniser.'],'analysis_errors'=>['error','Le classeur contient des erreurs bloquantes.'],'upload_error'=>['error','Le fichier n’a pas pu être enregistré.'],'invalid_file'=>['error','Veuillez sélectionner un fichier .xlsx.'],'file_too_large'=>['error','Le fichier dépasse la taille maximale de 20 Mo.'],'analysis_expired'=>['error','L’analyse a expiré. Veuillez déposer à nouveau le classeur.'],'analysis_cancelled'=>['success','Analyse annulée.'],'invalid_season'=>['error','Veuillez sélectionner une saison valide.'],'sync_success'=>['success','Synchronisation terminée avec succès.'],'sync_error'=>['error','La synchronisation a échoué et les modifications ont été annulées.']];
        if(isset($messages[$notice])){[$type,$text]=$messages[$notice];echo '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>'.esc_html($text).'</p></div>';}
        $result=get_transient($this->resultKey()); if(is_array($result)){delete_transient($this->resultKey()); if(!empty($result['stats'])) echo '<div class="notice notice-info"><p>'.esc_html($this->summaryText($result['stats'])).'</p></div>'; if(!empty($result['errors'])) echo '<div class="notice notice-error"><ul><li>'.implode('</li><li>',array_map('esc_html',$result['errors'])).'</li></ul></div>';}
    }

    private function summaryText(array $stats):string
    { $parts=[];foreach($stats as $entity=>$values){$created=(int)($values['created']??0);$updated=(int)($values['updated']??0);if($created||$updated)$parts[]=sprintf('%s : %d créé(s), %d mis à jour',ucfirst($entity),$created,$updated);}return $parts!==[]?implode(' — ',$parts):'Aucune modification.'; }
    private function findSeason(int $seasonId):?array
    {
        if($seasonId<=0) return null;
        foreach($this->seasonRepository->all() as $season){
            if((int)$season['id']===$seasonId) return $season;
        }
        return null;
    }
    private function stateKey():string{return 'e2n_sync_state_'.get_current_user_id();}
    private function resultKey():string{return 'e2n_sync_result_'.get_current_user_id();}
    private function redirect(string $notice):never{wp_safe_redirect(add_query_arg(['page'=>'ecole2nat-synchronization','e2n_notice'=>$notice],admin_url('admin.php')));exit;}
}
