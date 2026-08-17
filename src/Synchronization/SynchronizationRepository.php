<?php

namespace Ecole2Nat\Synchronization;

use Ecole2Nat\Support\Config;
use Ecole2Nat\Support\ScheduleDurationCalculator;

if (!defined('ABSPATH')) {
    exit;
}

final class SynchronizationRepository
{
    public function snapshot(): array
    {
        global $wpdb;
        return [
            'seasons' => $wpdb->get_results('SELECT * FROM ' . Config::table('seasons'), ARRAY_A) ?: [],
            'categories' => $wpdb->get_results('SELECT * FROM ' . Config::table('categories'), ARRAY_A) ?: [],
            'groups' => $wpdb->get_results('SELECT * FROM ' . Config::table('groups'), ARRAY_A) ?: [],
            'domains' => $wpdb->get_results('SELECT * FROM ' . Config::table('skill_domains'), ARRAY_A) ?: [],
            'skills' => $wpdb->get_results('SELECT * FROM ' . Config::table('skills'), ARRAY_A) ?: [],
            'exercises' => $wpdb->get_results('SELECT * FROM ' . Config::table('exercises'), ARRAY_A) ?: [],
            'swimmers' => $wpdb->get_results('SELECT * FROM ' . Config::table('swimmers'), ARRAY_A) ?: [],
            'season_skills' => $wpdb->get_results('SELECT * FROM ' . Config::table('season_skills'), ARRAY_A) ?: [],
            'memberships' => $wpdb->get_results('SELECT * FROM ' . Config::table('swimmer_group_memberships'), ARRAY_A) ?: [],
        ];
    }

    public function estimate(array $data, array $season): array
    {
        $snapshot = $this->snapshot();
        $plan = $this->emptyStats();
        $categoryMap = [];
        foreach ($snapshot['categories'] as $row) $categoryMap[$this->normalize($row['name'])] = $row;
        $groupMap = [];
        foreach ($snapshot['groups'] as $row) $groupMap[(int)$row['season_id'].'|'.(int)$row['category_id'].'|'.$this->normalize($row['name'])] = $row;
        $domainMap = [];
        foreach ($snapshot['domains'] as $row) $domainMap[(int)$row['category_id'].'|'.$this->normalize($row['name'])] = $row;
        $skillMap = [];
        foreach ($snapshot['skills'] as $row) $skillMap[(int)$row['domain_id'].'|'.$this->normalize($row['name'])] = $row;
        $exerciseMap = [];
        foreach ($snapshot['exercises'] as $row) $exerciseMap[(int)$row['skill_id'].'|'.$this->normalize($row['name'])] = $row;
        $swimmerLicence = []; $swimmerIdentity = [];
        foreach ($snapshot['swimmers'] as $row) {
            if (!empty($row['licence_number'])) $swimmerLicence[$this->normalize($row['licence_number'])] = $row;
            $swimmerIdentity[$this->key($row['last_name'],$row['first_name'],(string)$row['birth_date'])] = $row;
        }
        $virtualCategories=[]; $virtualDomains=[]; $virtualSkills=[];
        $seasonId = (int) ($season['id'] ?? 0);
        $plan['seasons']['unchanged']++;
        foreach ($data['groups'] as $row) {
            $ck=$this->normalize($row['category']);
            if (!isset($virtualCategories[$ck])) { $virtualCategories[$ck]=isset($categoryMap[$ck])?(int)$categoryMap[$ck]['id']:-count($virtualCategories)-1; $plan['categories'][isset($categoryMap[$ck])?'unchanged':'created']++; }
            $gk=$seasonId.'|'.$virtualCategories[$ck].'|'.$this->normalize($row['name']);
            $existingGroup = $groupMap[$gk] ?? null;
            if (!$existingGroup) {
                $plan['groups']['created']++;
            } else {
                $effectiveStart = !empty($existingGroup['start_time']) ? (string) $existingGroup['start_time'] : (string) ($row['startTime'] ?? '');
                $expectedEnd = isset($row['durationMinutes']) && $row['durationMinutes'] !== null ? ScheduleDurationCalculator::endTime($effectiveStart, (int) $row['durationMinutes']) : null;
                $needsRepair = (empty($existingGroup['weekday']) && !empty($row['weekday'])) || (empty($existingGroup['start_time']) && !empty($row['startTime']));
                $durationChanges = $expectedEnd !== null && substr((string) ($existingGroup['end_time'] ?? ''), 0, 8) !== $expectedEnd;
                $plan['groups'][$needsRepair || $durationChanges ? 'updated' : 'unchanged']++;
            }
        }
        foreach ($data['reference'] as $row) {
            $ck=$this->normalize($row['category']);
            if (!isset($virtualCategories[$ck])) { $virtualCategories[$ck]=isset($categoryMap[$ck])?(int)$categoryMap[$ck]['id']:-count($virtualCategories)-1; $plan['categories'][isset($categoryMap[$ck])?'unchanged':'created']++; }
            $dk=$virtualCategories[$ck].'|'.$this->normalize($row['domain']);
            if (!isset($virtualDomains[$dk])) { $virtualDomains[$dk]=isset($domainMap[$dk])?(int)$domainMap[$dk]['id']:-count($virtualDomains)-1; $plan['domains'][isset($domainMap[$dk])?'unchanged':'created']++; }
            $sk=$virtualDomains[$dk].'|'.$this->normalize($row['skill']);
            if (!isset($virtualSkills[$sk])) { $virtualSkills[$sk]=isset($skillMap[$sk])?(int)$skillMap[$sk]['id']:-count($virtualSkills)-1; $plan['skills'][isset($skillMap[$sk])?'unchanged':'created']++; }
            foreach ($row['exercises'] as $name) $plan['exercises'][isset($exerciseMap[$virtualSkills[$sk].'|'.$this->normalize($name)])?'unchanged':'created']++;
        }
        foreach ($data['swimmers'] as $row) {
            $existing = $row['licence_number']!=='' ? ($swimmerLicence[$this->normalize($row['licence_number'])]??null) : ($swimmerIdentity[$this->key($row['last_name'],$row['first_name'],(string)$row['birth_date'])]??null);
            $plan['swimmers'][$existing?'updated':'created']++;
        }
        return $plan;
    }

    public function synchronize(array $data, int $userId, string $filename, array $season): array
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $stats = $this->emptyStats();
        try {
            $stats['seasons']['unchanged']++;
            $categoryIds = $this->syncCategories($data, $stats);
            $groupIds = $this->syncGroups($data['groups'], (int) $season['id'], $categoryIds, $stats);
            [$domainIds, $skillIds] = $this->syncReference($data['reference'], $categoryIds, $stats);
            $this->syncSeasonSkills((int) $season['id'], $skillIds, $stats);
            $this->syncExercises($data['reference'], $skillIds, $stats);
            $this->syncSwimmers($data['swimmers'], $groupIds, (int) $season['id'], $stats);
            $wpdb->query('COMMIT');
            $this->log($filename, $userId, 'success', $stats, []);
            return ['success' => true, 'stats' => $stats, 'errors' => []];
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            $errors = [$exception->getMessage()];
            $this->log($filename, $userId, 'error', $stats, $errors);
            return ['success' => false, 'stats' => $stats, 'errors' => $errors];
        }
    }

    public function recentLogs(int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . Config::table('synchronization_logs') . ' ORDER BY id DESC LIMIT %d', $limit),
            ARRAY_A
        ) ?: [];
    }

    private function syncCategories(array $data, array &$stats): array
    {
        global $wpdb;
        $table = Config::table('categories');
        $names = [];
        foreach ($data['groups'] as $row) $names[] = $row['category'];
        foreach ($data['reference'] as $row) $names[] = $row['category'];
        $ids = [];
        foreach (array_values(array_unique(array_filter($names))) as $name) {
            $key = $this->normalize($name);
            $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE LOWER(name)=LOWER(%s) LIMIT 1", $name));
            if ($id <= 0) {
                if ($wpdb->insert($table, ['name'=>$name,'description'=>'','sort_order'=>0,'is_active'=>1,'created_at'=>current_time('mysql')], ['%s','%s','%d','%d','%s']) === false) {
                    throw new \RuntimeException('Impossible de créer la catégorie ' . $name . ' : ' . $wpdb->last_error);
                }
                $id = (int) $wpdb->insert_id;
                $stats['categories']['created']++;
            } else $stats['categories']['unchanged']++;
            $ids[$key] = $id;
        }
        return $ids;
    }

    private function syncGroups(array $groups, int $seasonId, array $categoryIds, array &$stats): array
    {
        global $wpdb;
        $table = Config::table('groups');
        $ids = [];
        foreach ($groups as $row) {
            $categoryId = $categoryIds[$this->normalize($row['category'])] ?? 0;
            $key = $this->key($row['category'], $row['name']);
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE season_id=%d AND category_id=%d AND LOWER(name)=LOWER(%s) LIMIT 1", $seasonId, $categoryId, $row['name']), ARRAY_A);
            $weekday = isset($row['weekday']) && $row['weekday'] !== null ? (int) $row['weekday'] : null;
            $startTime = !empty($row['startTime']) ? (string) $row['startTime'] : null;
            $durationMinutes = isset($row['durationMinutes']) && $row['durationMinutes'] !== null ? (int) $row['durationMinutes'] : null;
            $endTime = $durationMinutes !== null && $startTime !== null ? ScheduleDurationCalculator::endTime($startTime, $durationMinutes) : null;

            if (!$existing) {
                if ($wpdb->insert($table, [
                    'season_id'=>$seasonId,
                    'category_id'=>$categoryId,
                    'name'=>$row['name'],
                    'color'=>null,
                    'weekday'=>$weekday,
                    'start_time'=>$startTime,
                    'end_time'=>$endTime,
                    'is_active'=>1,
                    'created_at'=>current_time('mysql')
                ], ['%d','%d','%s','%s','%d','%s','%s','%d','%s']) === false) {
                    throw new \RuntimeException('Impossible de créer le groupe ' . $row['name'] . ' : ' . $wpdb->last_error);
                }
                $id=(int)$wpdb->insert_id; $stats['groups']['created']++;
            } else {
                $id=(int)$existing['id'];
                $needsScheduleRepair = (empty($existing['weekday']) || empty($existing['start_time'])) && ($weekday !== null || $startTime !== null);
                $effectiveStart = !empty($existing['start_time']) ? (string) $existing['start_time'] : $startTime;
                $synchronizedEnd = $durationMinutes !== null && $effectiveStart !== null ? ScheduleDurationCalculator::endTime($effectiveStart, $durationMinutes) : null;
                $durationChanges = $synchronizedEnd !== null && substr((string) ($existing['end_time'] ?? ''), 0, 8) !== $synchronizedEnd;

                if ($needsScheduleRepair || $durationChanges) {
                    $payload = [
                        'updated_at' => current_time('mysql'),
                    ];
                    $formats = ['%s'];
                    if ($needsScheduleRepair) {
                        $payload['weekday'] = $weekday;
                        $payload['start_time'] = $startTime;
                        $formats[] = '%d';
                        $formats[] = '%s';
                    }
                    if ($durationChanges) {
                        $payload['end_time'] = $synchronizedEnd;
                        $formats[] = '%s';
                    }
                    if ($wpdb->update(
                        $table,
                        $payload,
                        ['id' => $id],
                        $formats,
                        ['%d']
                    ) === false) throw new \RuntimeException('Impossible de mettre à jour le créneau du groupe ' . $row['name'] . ' : ' . $wpdb->last_error);
                    $stats['groups']['updated']++;
                } else {
                    $stats['groups']['unchanged']++;
                }
            }
            $ids[$key]=$id;
            $ids[$this->key($row['category'], $row['name'])]=$id;
        }
        return $ids;
    }

    private function syncReference(array $rows, array $categoryIds, array &$stats): array
    {
        global $wpdb;
        $domainTable=Config::table('skill_domains'); $skillTable=Config::table('skills');
        $domainIds=[]; $skillIds=[];
        foreach ($rows as $row) {
            $categoryId=$categoryIds[$this->normalize($row['category'])] ?? 0;
            $domainKey=$this->key($row['category'],$row['domain']);
            if (!isset($domainIds[$domainKey])) {
                $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$domainTable} WHERE category_id=%d AND LOWER(name)=LOWER(%s) LIMIT 1",$categoryId,$row['domain']));
                if ($id<=0) {
                    if ($wpdb->insert($domainTable,['category_id'=>$categoryId,'name'=>$row['domain'],'description'=>'','sort_order'=>0,'is_active'=>1,'created_at'=>current_time('mysql')],['%d','%s','%s','%d','%d','%s'])===false) throw new \RuntimeException('Impossible de créer le domaine '.$row['domain'].'.');
                    $id=(int)$wpdb->insert_id; $stats['domains']['created']++;
                } else $stats['domains']['unchanged']++;
                $domainIds[$domainKey]=$id;
            }
            $skillKey=$this->key($row['category'],$row['domain'],$row['skill']);
            if (!isset($skillIds[$skillKey])) {
                $domainId=$domainIds[$domainKey];
                $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$skillTable} WHERE domain_id=%d AND LOWER(name)=LOWER(%s) LIMIT 1",$domainId,$row['skill']));
                if ($id<=0) {
                    if ($wpdb->insert($skillTable,['domain_id'=>$domainId,'name'=>$row['skill'],'description'=>'','sort_order'=>0,'is_active'=>1,'created_at'=>current_time('mysql')],['%d','%s','%s','%d','%d','%s'])===false) throw new \RuntimeException('Impossible de créer la compétence '.$row['skill'].'.');
                    $id=(int)$wpdb->insert_id; $stats['skills']['created']++;
                } else $stats['skills']['unchanged']++;
                $skillIds[$skillKey]=$id;
            }
        }
        return [$domainIds,$skillIds];
    }

    private function syncSeasonSkills(int $seasonId, array $skillIds, array &$stats): void
    {
        global $wpdb;
        $table=Config::table('season_skills');
        foreach(array_unique(array_map('intval',array_values($skillIds))) as $skillId){
            if($skillId<=0) continue;
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE season_id=%d AND skill_id=%d LIMIT 1",$seasonId,$skillId));
            if($exists>0){
                $wpdb->update($table,['is_active'=>1,'updated_at'=>current_time('mysql')],['id'=>$exists],['%d','%s'],['%d']);
                $stats['season_skills']['unchanged']++;
                continue;
            }
            if($wpdb->insert($table,['season_id'=>$seasonId,'skill_id'=>$skillId,'is_active'=>1,'created_at'=>current_time('mysql')],['%d','%d','%d','%s'])===false){
                throw new \RuntimeException('Impossible d’associer une compétence à la saison : '.$wpdb->last_error);
            }
            $stats['season_skills']['created']++;
        }
    }

    private function syncExercises(array $rows, array $skillIds, array &$stats): void
    {
        global $wpdb; $table=Config::table('exercises'); $seen=[];
        foreach ($rows as $row) {
            $skillId=$skillIds[$this->key($row['category'],$row['domain'],$row['skill'])] ?? 0;
            foreach ($row['exercises'] as $name) {
                $key=$skillId.'|'.$this->normalize($name); if(isset($seen[$key])) continue; $seen[$key]=true;
                $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE skill_id=%d AND LOWER(name)=LOWER(%s) LIMIT 1",$skillId,$name));
                if($id<=0){
                    if($wpdb->insert($table,['skill_id'=>$skillId,'name'=>$name,'description'=>'','objectives'=>'','coach_notes'=>'','equipment'=>'','difficulty'=>1,'created_at'=>current_time('mysql')],['%d','%s','%s','%s','%s','%s','%d','%s'])===false) throw new \RuntimeException('Impossible de créer l’exercice '.$name.'.');
                    $stats['exercises']['created']++;
                } else $stats['exercises']['unchanged']++;
            }
        }
    }

    private function syncSwimmers(array $rows, array $groupIds, int $seasonId, array &$stats): void
    {
        global $wpdb; $table=Config::table('swimmers');
        foreach($rows as $row){
            $groupId=$groupIds[$this->key($row['category'],$row['group_name'])] ?? 0;
            if($groupId<=0 && $row['category']!=='' && $row['slot']!=='') throw new \RuntimeException('Groupe introuvable pour '.$row['first_name'].' '.$row['last_name'].' : '.$row['group_name']);
            $existing=null;
            if($row['licence_number']!=='') $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE licence_number=%s LIMIT 1",$row['licence_number']),ARRAY_A);
            if(!$existing && $row['birth_date']) $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE LOWER(last_name)=LOWER(%s) AND LOWER(first_name)=LOWER(%s) AND birth_date=%s LIMIT 1",$row['last_name'],$row['first_name'],$row['birth_date']),ARRAY_A);
            $payload=['group_id'=>$groupId?:null,'last_name'=>$row['last_name'],'first_name'=>$row['first_name'],'birth_date'=>$row['birth_date'],'gender'=>$row['gender'],'responsible_email'=>$row['responsible_email'],'responsible_phone'=>$row['responsible_phone'],'licence_number'=>$row['licence_number'],'medical_note'=>$row['medical_note'],'image_rights'=>$row['image_rights'],'registration_date'=>current_time('Y-m-d'),'is_active'=>1];
            if(!$existing){$payload['created_at']=current_time('mysql'); if($wpdb->insert($table,$payload)===false) throw new \RuntimeException('Impossible de créer '.$row['first_name'].' '.$row['last_name'].' : '.$wpdb->last_error); $swimmerId=(int)$wpdb->insert_id; $stats['swimmers']['created']++;}
            else { $swimmerId=(int)$existing['id']; $payload['updated_at']=current_time('mysql'); $changed=false; foreach($payload as $k=>$v){if($k==='updated_at')continue;if((string)($existing[$k]??'')!==(string)($v??'')){$changed=true;break;}} if($changed){if($wpdb->update($table,$payload,['id'=>$swimmerId])===false) throw new \RuntimeException('Impossible de mettre à jour '.$row['first_name'].' '.$row['last_name'].' : '.$wpdb->last_error);$stats['swimmers']['updated']++;}else$stats['swimmers']['unchanged']++;}
            if($groupId>0){
                $membership=Config::table('swimmer_group_memberships');
                $sql=$wpdb->prepare("INSERT INTO {$membership} (swimmer_id,season_id,group_id,created_at,updated_at) VALUES (%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE group_id=VALUES(group_id),updated_at=VALUES(updated_at)",$swimmerId,$seasonId,$groupId,current_time('mysql'),current_time('mysql'));
                if($wpdb->query($sql)===false) throw new \RuntimeException('Impossible d’historiser le groupe de '.$row['first_name'].' '.$row['last_name'].' : '.$wpdb->last_error);
            }
        }
    }

    private function log(string $filename,int $userId,string $status,array $stats,array $errors):void
    { global $wpdb; $wpdb->insert(Config::table('synchronization_logs'),['filename'=>$filename,'status'=>$status,'summary'=>wp_json_encode($stats),'errors'=>wp_json_encode($errors),'created_by'=>$userId,'created_at'=>current_time('mysql')],['%s','%s','%s','%s','%d','%s']); }
    private function emptyStats():array{ $keys=['seasons','categories','groups','domains','skills','season_skills','exercises','swimmers'];$r=[];foreach($keys as $k)$r[$k]=['created'=>0,'updated'=>0,'unchanged'=>0];return$r; }
    private function key(string ...$parts):string{return implode('|',array_map([$this,'normalize'],$parts));}
    private function normalize(string $value):string{$value=remove_accents(mb_strtolower(trim($value)));return trim(preg_replace('/\s+/',' ',preg_replace('/[^a-z0-9]+/u',' ',$value)?:'')?:'');}
}
