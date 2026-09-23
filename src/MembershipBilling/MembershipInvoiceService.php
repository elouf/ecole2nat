<?php

namespace Ecole2Nat\MembershipBilling;

use Dompdf\Dompdf;
use Dompdf\Options;
use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\ContactList;

if (!defined('ABSPATH')) { exit; }

final class MembershipInvoiceService
{
    public function seasons(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT id,name,is_current FROM '.Config::table('seasons').' ORDER BY start_date DESC,id DESC', ARRAY_A) ?: [];
    }

    public function rows(int $seasonId, string $paid, string $sent, string $search): array
    {
        global $wpdb;
        $payments=Config::table('membership_payments');$swimmers=Config::table('swimmers');$groups=Config::table('groups');$invoices=Config::table('membership_invoices');
        $where=['p.season_id=%d'];$args=[$seasonId];
        if(in_array($paid,['yes','no'],true)){$where[]='p.paid_flag=%d';$args[]=$paid==='yes'?1:0;}
        if($sent==='yes')$where[]='(p.invoice_marked_sent=1 OR i.sent_at IS NOT NULL)';
        if($sent==='no')$where[]='p.invoice_marked_sent=0 AND i.sent_at IS NULL';
        if($search!==''){$like='%'.$wpdb->esc_like($search).'%';$where[]='(s.first_name LIKE %s OR s.last_name LIKE %s OR s.responsible_email LIKE %s)';array_push($args,$like,$like,$like);}
        $sql='SELECT p.*,s.first_name,s.last_name,s.responsible_email,g.name group_name,i.id invoice_id,i.invoice_number,i.sent_at,i.sent_to FROM '.$payments.' p INNER JOIN '.$swimmers.' s ON s.id=p.swimmer_id LEFT JOIN '.$groups.' g ON g.id=s.group_id LEFT JOIN '.$invoices.' i ON i.season_id=p.season_id AND i.swimmer_id=p.swimmer_id WHERE '.implode(' AND ',$where).' ORDER BY s.last_name,s.first_name';
        return $wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];
    }

    public function invoice(int $id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Config::table('membership_invoices').' WHERE id=%d',$id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function prepare(int $seasonId,int $swimmerId,int $userId): array
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try{
            $payment=$wpdb->get_row($wpdb->prepare('SELECT p.*,s.first_name,s.last_name,s.responsible_email,se.name season_name FROM '.Config::table('membership_payments').' p INNER JOIN '.Config::table('swimmers').' s ON s.id=p.swimmer_id INNER JOIN '.Config::table('seasons').' se ON se.id=p.season_id WHERE p.season_id=%d AND p.swimmer_id=%d FOR UPDATE',$seasonId,$swimmerId),ARRAY_A);
            if(!$payment)throw new \RuntimeException(__('Règlement introuvable.','ecole2nat'));
            if((int)$payment['paid_flag']!==1)throw new \RuntimeException(__('La colonne « Payé ? » doit contenir OUI.','ecole2nat'));
            if((float)$payment['total_paid']<=0)throw new \RuntimeException(__('Le total versé doit être supérieur à zéro.','ecole2nat'));
            $table=Config::table('membership_invoices');$existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE season_id=%d AND swimmer_id=%d FOR UPDATE",$seasonId,$swimmerId),ARRAY_A);$now=current_time('mysql');
            $data=['total_amount'=>$payment['total_paid'],'swimmer_name'=>trim($payment['first_name'].' '.$payment['last_name']),'season_name'=>$payment['season_name'],'issuer_name'=>Config::invoiceIssuerName(),'issuer_address'=>Config::invoiceIssuerAddress(),'issuer_siret'=>Config::invoiceIssuerSiret(),'issuer_logo_id'=>Config::invoiceLogoId(),'responsible_emails'=>implode(', ',ContactList::emails((string)$payment['responsible_email'])),'updated_at'=>$now];
            if(!$existing){$data+=['season_id'=>$seasonId,'swimmer_id'=>$swimmerId,'invoice_number'=>$this->nextNumber((int)current_time('Y'),$now),'issued_on'=>current_time('Y-m-d'),'generated_at'=>$now,'generated_by'=>$userId,'created_at'=>$now];if($wpdb->insert($table,$data)===false)throw new \RuntimeException($wpdb->last_error);$id=(int)$wpdb->insert_id;}
            else{$id=(int)$existing['id'];if(empty($existing['sent_at'])&&$wpdb->update($table,$data,['id'=>$id])===false)throw new \RuntimeException($wpdb->last_error);}
            $wpdb->query('COMMIT');return ['success'=>true,'invoice_id'=>$id];
        }catch(\Throwable $e){$wpdb->query('ROLLBACK');return ['success'=>false,'message'=>$e->getMessage()];}
    }

    public function send(int $seasonId,int $swimmerId,int $userId): array
    {
        $prepared=$this->prepare($seasonId,$swimmerId,$userId);if(!$prepared['success'])return $prepared;
        $invoice=$this->invoice((int)$prepared['invoice_id']);if(!$invoice)return ['success'=>false,'message'=>__('Facture introuvable.','ecole2nat')];
        if(!empty($invoice['sent_at']))return ['success'=>false,'message'=>__('Cette facture a déjà été envoyée.','ecole2nat')];
        $emails=ContactList::emails((string)$invoice['responsible_emails']);if($emails===[])return ['success'=>false,'message'=>__('Aucune adresse email responsable valide.','ecole2nat')];
        $temp=wp_tempnam('facture-'.$invoice['invoice_number'].'.pdf');if(!$temp)return ['success'=>false,'message'=>__('Impossible de créer le PDF temporaire.','ecole2nat')];
        $pdfPath=preg_replace('/\.[^.]+$/','.pdf',$temp)?:($temp.'.pdf');if($pdfPath!==$temp)@rename($temp,$pdfPath);
        try{
            $options=new Options();$options->set('defaultFont','DejaVu Sans');$options->set('isRemoteEnabled',false);$dompdf=new Dompdf($options);$dompdf->loadHtml($this->document($invoice));$dompdf->setPaper('A4');$dompdf->render();
            if(file_put_contents($pdfPath,$dompdf->output())===false)throw new \RuntimeException(__('Impossible d’écrire le PDF.','ecole2nat'));
            $subject=sprintf(__('Facture acquittée – adhésion %1$s – %2$s','ecole2nat'),$invoice['season_name'],$invoice['swimmer_name']);
            $body=sprintf("Bonjour,\n\nVeuillez trouver en pièce jointe la facture acquittée correspondant à l’adhésion de %s pour la saison %s.\n\nBien cordialement,\n%s",$invoice['swimmer_name'],$invoice['season_name'],Config::invoiceIssuerName());
            $from=Config::treasurerEmail();$headers=['Content-Type: text/plain; charset=UTF-8','From: '.Config::invoiceIssuerName().' <'.$from.'>','Reply-To: '.$from];
            if(!wp_mail($emails,$subject,$body,$headers,[$pdfPath]))throw new \RuntimeException(__('WordPress n’a pas pu envoyer cet email.','ecole2nat'));
            global $wpdb;$now=current_time('mysql');if($wpdb->update(Config::table('membership_invoices'),['sent_at'=>$now,'sent_by'=>$userId,'sent_to'=>implode(', ',$emails),'updated_at'=>$now],['id'=>(int)$invoice['id']])===false)throw new \RuntimeException(__('Email envoyé, mais son statut n’a pas pu être enregistré.','ecole2nat'));
            return ['success'=>true,'invoice_id'=>(int)$invoice['id']];
        }catch(\Throwable $e){return ['success'=>false,'message'=>$e->getMessage()];}finally{if(is_file($pdfPath))@unlink($pdfPath);}
    }

    public function document(array $invoice): string
    {
        $logo='';$path=$invoice['issuer_logo_id']?get_attached_file((int)$invoice['issuer_logo_id']):'';
        if(is_string($path)&&is_file($path)){$mime=mime_content_type($path)?:'image/png';$logo='<img class="logo" src="data:'.esc_attr($mime).';base64,'.base64_encode((string)file_get_contents($path)).'">';}
        $sent=!empty($invoice['sent_at'])?'<p class="sent">Envoyée le '.esc_html(wp_date('d/m/Y H:i',strtotime($invoice['sent_at']))).'</p>':'';
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>@page{margin:18mm}body{font-family:DejaVu Sans,sans-serif;color:#17212f;font-size:12px}.top{display:table;width:100%}.top>div{display:table-cell;width:55%;vertical-align:top}.top>.recipient{width:45%}.logo{max-width:150px;max-height:85px}.issuer{white-space:pre-line;line-height:1.55}.recipient{border:1px solid #ccd5df;padding:16px;font-size:15px}.meta{margin:32px 0 24px;display:table;width:100%}.meta>strong,.meta>div{display:table-cell;vertical-align:top}.meta>div{text-align:right}.meta strong{font-size:22px}table{width:100%;border-collapse:collapse;margin-top:20px}th{background:#e9eef4;text-align:left}th,td{border:1px solid #cbd3dc;padding:12px}.amount{text-align:right}.paid{text-align:center;font-size:64px;font-weight:bold;color:#d8dde3;margin:80px 0 50px;transform:rotate(-8deg)}.total{margin-left:auto;width:42%;font-size:16px}.total td:last-child{text-align:right;font-weight:bold}.footer{position:fixed;bottom:0;left:0;right:0;border-top:1px solid #ccd5df;padding-top:10px;text-align:center;color:#5b6675;font-size:10px}.sent{color:#178447;font-weight:bold}</style></head><body><div class="top"><div>'.$logo.'<div class="issuer"><strong>'.esc_html($invoice['issuer_name']).'</strong><br>'.nl2br(esc_html($invoice['issuer_address'])).($invoice['issuer_siret']?'<br>SIRET : '.esc_html($invoice['issuer_siret']):'').'</div></div><div class="recipient"><strong>'.esc_html($invoice['swimmer_name']).'</strong><br>Adhésion saison '.esc_html($invoice['season_name']).'</div></div><div class="meta"><strong>FACTURE ACQUITTÉE</strong><div>Facture n° '.esc_html($invoice['invoice_number']).'<br>Date : '.esc_html(wp_date('d/m/Y',strtotime($invoice['issued_on']))).$sent.'</div></div><table><thead><tr><th>Désignation</th><th class="amount">Quantité</th><th class="amount">Prix unitaire</th><th class="amount">Total</th></tr></thead><tbody><tr><td>Adhésion '.esc_html($invoice['swimmer_name']).' — saison '.esc_html($invoice['season_name']).'</td><td class="amount">1</td><td class="amount">'.esc_html(number_format((float)$invoice['total_amount'],2,',',' ')).' €</td><td class="amount">'.esc_html(number_format((float)$invoice['total_amount'],2,',',' ')).' €</td></tr></tbody></table><div class="paid">PAYÉ</div><table class="total"><tr><td>Net à payer</td><td>'.esc_html(number_format((float)$invoice['total_amount'],2,',',' ')).' €</td></tr></table><div class="footer">'.esc_html($invoice['issuer_name']).' — Facture acquittée</div></body></html>';
    }

    private function nextNumber(int $year,string $now): string
    {global $wpdb;$table=Config::table('invoice_sequences');if($wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (calendar_year,last_number,updated_at) VALUES (%d,999,%s)",$year,$now))===false)throw new \RuntimeException('sequence_create');$last=$wpdb->get_var($wpdb->prepare("SELECT last_number FROM {$table} WHERE calendar_year=%d FOR UPDATE",$year));if($last===null)throw new \RuntimeException('sequence_read');$next=max(999,(int)$last)+1;if($wpdb->update($table,['last_number'=>$next,'updated_at'=>$now],['calendar_year'=>$year],['%d','%s'],['%d'])===false)throw new \RuntimeException('sequence_update');return sprintf('F%02d.%d',$year%100,$next);}
}
